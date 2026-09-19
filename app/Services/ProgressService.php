<?php

namespace App\Services;

use App\Models\GestureLog;
use App\Models\Level;
use App\Models\PlayerProfile;
use App\Models\Progress;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\Sign;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    /**
     * Completion threshold for marking a sign as mastered.
     */
    private const COMPLETION_THRESHOLD = 80.00;

    /**
     * Get all progress entries for a user.
     */
    public function getUserProgress(int $userId): Collection
    {
        return Progress::where('user_id', $userId)
                       ->with(['sign', 'level'])
                       ->orderBy('updated_at', 'desc')
                       ->get();
    }

    /**
     * Get user's progress for a specific level.
     */
    public function getUserProgressByLevel(int $userId, int $levelId): Collection
    {
        return Progress::where('user_id', $userId)
                       ->where('level_id', $levelId)
                       ->with('sign')
                       ->get();
    }

    /**
     * Get user's progress for a specific sign.
     */
    public function getUserProgressBySign(int $userId, int $signId): ?Progress
    {
        return Progress::where('user_id', $userId)
                       ->where('sign_id', $signId)
                       ->with(['sign', 'level'])
                       ->first();
    }

    /**
     * Check if user has recorded valid practice/gesture evidence for the given sign.
     */
    public function hasValidPracticeEvidence(int $userId, Sign $sign): bool
    {
        return GestureLog::where('user_id', $userId)
            ->where('sign_id', $sign->id)
            ->where('is_correct', true)
            ->where('confidence', '>=', self::COMPLETION_THRESHOLD)
            ->exists();
    }

    /**
     * Check if user has passed the required quiz for the given sign.
     */
    public function hasValidQuizEvidence(int $userId, Sign $sign): bool
    {
        $questionIds = Question::where('sign_id', $sign->id)->pluck('id');

        // A sign needs its own mapped question. A correct answer for another
        // question in the same quiz must never unlock this sign.
        if ($questionIds->isEmpty()) {
            return false;
        }

        return QuizAttempt::where('user_id', $userId)
            ->whereIn('question_id', $questionIds)
            ->where('score', '>', 0)
            ->exists();
    }

    /**
     * Determine if a sign satisfies both practice and quiz criteria for completion.
     */
    public function canCompleteSign(int $userId, Sign $sign): bool
    {
        return $this->hasValidPracticeEvidence($userId, $sign)
            && $this->hasValidQuizEvidence($userId, $sign);
    }

    /**
     * Increment attempts and update best confidence based on a new gesture attempt.
     * Completion is evaluated separately after the corresponding evidence is stored.
     */
    public function recordAttempt(int $userId, int $signId, int $levelId, float $confidence): Progress
    {
        return DB::transaction(function () use ($userId, $signId, $levelId, $confidence) {
            $sign = Sign::findOrFail($signId);
            if ((int) $sign->level_id !== (int) $levelId) {
                throw ValidationException::withMessages([
                    'sign_id' => 'The selected sign does not belong to the specified level.',
                ]);
            }

            $progress = Progress::where('user_id', $userId)
                ->where('sign_id', $signId)
                ->lockForUpdate()
                ->first();

            if (! $progress) {
                $progress = new Progress([
                    'user_id' => $userId,
                    'sign_id' => $signId,
                ]);
            }

            $progress->level_id = $levelId;
            $progress->attempts = ($progress->attempts ?? 0) + 1;

            if ($confidence > (float) ($progress->best_confidence ?? 0)) {
                $progress->best_confidence = $confidence;
            }

            $progress->save();

            $this->recordDailyStreak($userId);

            return $progress->load(['sign', 'level']);
        });
    }

    /**
     * Create or retrieve progress metadata. Player input cannot complete a sign
     * or award XP; evidence routes evaluate completion themselves.
     */
    public function updateProgress(int $userId, array $data): Progress
    {
        return DB::transaction(function () use ($userId, $data) {
            $sign = Sign::findOrFail($data['sign_id']);

            // Validate that sign_id belongs to level_id server-side
            if ((int) $sign->level_id !== (int) $data['level_id']) {
                throw ValidationException::withMessages([
                    'sign_id' => 'The selected sign does not belong to the specified level.',
                ]);
            }

            $progress = Progress::where('user_id', $userId)
                ->where('sign_id', $data['sign_id'])
                ->lockForUpdate()
                ->first();

            if (! $progress) {
                $progress = new Progress([
                    'user_id' => $userId,
                    'sign_id' => $data['sign_id'],
                ]);
            }

            $progress->level_id = $data['level_id'];

            $progress->save();

            $this->recordDailyStreak($userId);

            return $progress->load(['sign', 'level']);
        });
    }

    /**
     * Evaluate completion only after a gesture or quiz attempt has been stored.
     */
    public function evaluateCompletion(int $userId, int $signId): ?Progress
    {
        return DB::transaction(function () use ($userId, $signId) {
            $sign = Sign::findOrFail($signId);
            $progress = Progress::where('user_id', $userId)
                ->where('sign_id', $signId)
                ->lockForUpdate()
                ->first();

            if (! $progress || $progress->is_completed || ! $this->canCompleteSign($userId, $sign)) {
                return $progress?->load(['sign', 'level']);
            }

            $progress->is_completed = true;
            $this->awardXpAndAdvanceLevel($userId, $progress, $sign);
            $progress->save();

            return $progress->load(['sign', 'level']);
        });
    }

    /**
     * Award XP once for a sign and recalculate player level.
     */
    protected function awardXpAndAdvanceLevel(int $userId, Progress $progress, Sign $sign): void
    {
        // Server-side duplicate protection: only award XP if not already awarded
        if (! is_null($progress->xp_awarded_at)) {
            return;
        }

        $xpReward = (int) ($sign->xp_reward ?? 0);

        $profile = PlayerProfile::where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $profile) {
            $profile = PlayerProfile::create([
                'user_id'       => $userId,
                'current_level' => 1,
                'total_xp'      => 0,
                'streak'        => 0,
                'hearts'        => 5,
            ]);
        }

        $profile->total_xp = ($profile->total_xp ?? 0) + $xpReward;

        // Level thresholds are admin-managed data and are never changed during play.
        $highestLevel = Level::where('required_xp', '<=', $profile->total_xp)
            ->where(function ($query) {
                $query->where('order', 1)
                    ->orWhere('required_xp', '>', 0);
            })
            ->orderBy('required_xp', 'desc')
            ->orderBy('order', 'desc')
            ->first();

        if ($highestLevel) {
            $newLevel = $highestLevel->order ?: $highestLevel->id;
            $profile->current_level = max($profile->current_level ?? 1, $newLevel);
        }

        $profile->save();

        $this->recordDailyStreak($userId, $profile);

        $progress->xp_awarded_at = now();
    }

    /**
     * Record daily practice activity and advance streak.
     */
    public function recordDailyStreak(int $userId, ?PlayerProfile $profile = null): int
    {
        $today = now()->startOfDay();

        if (! $profile) {
            $profile = PlayerProfile::where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $profile = PlayerProfile::create([
                    'user_id'          => $userId,
                    'current_level'    => 1,
                    'total_xp'         => 0,
                    'streak'           => 1,
                    'hearts'           => 5,
                    'last_played_date' => $today,
                ]);
                return 1;
            }
        }

        if (! $profile->last_played_date) {
            $profile->streak = 1;
            $profile->last_played_date = $today;
            $profile->save();
            return 1;
        }

        $lastPlayed = $profile->last_played_date->copy()->startOfDay();

        if ($lastPlayed->isToday()) {
            // Already practiced today; maintain streak
            return (int) $profile->streak;
        }

        if ($lastPlayed->isYesterday()) {
            // Consecutive day practice (+1)
            $profile->streak = (int) ($profile->streak ?? 0) + 1;
        } else {
            // Missed a day or more, restart streak at 1
            $profile->streak = 1;
        }

        $profile->last_played_date = $today;
        $profile->save();

        return (int) $profile->streak;
    }

    /**
     * Calculate completion percentage for a level.
     */
    public function getLevelCompletionPercentage(int $userId, int $levelId, int $totalSigns): float
    {
        if ($totalSigns === 0) {
            return 0.0;
        }

        $completed = Progress::where('user_id', $userId)
                             ->where('level_id', $levelId)
                             ->where('is_completed', true)
                             ->count();

        return round(($completed / $totalSigns) * 100, 2);
    }
}
