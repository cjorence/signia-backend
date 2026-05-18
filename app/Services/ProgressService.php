<?php

namespace App\Services;

use App\Models\Progress;
use Illuminate\Database\Eloquent\Collection;

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
     * Increment attempts and update best confidence based on a new gesture attempt.
     * Auto-marks sign as completed when threshold is reached.
     */
    public function recordAttempt(int $userId, int $signId, int $levelId, float $confidence): Progress
    {
        $progress = Progress::firstOrNew([
            'user_id' => $userId,
            'sign_id' => $signId,
        ]);

        // Ensure level_id is set (preserves existing or assigns new)
        $progress->level_id = $levelId;

        // Increment attempts
        $progress->attempts = ($progress->attempts ?? 0) + 1;

        // Update best confidence only if the new one is higher
        if ($confidence > (float) ($progress->best_confidence ?? 0)) {
            $progress->best_confidence = $confidence;
        }

        // Auto-complete if threshold reached
        if ((float) $progress->best_confidence >= self::COMPLETION_THRESHOLD) {
            $progress->is_completed = true;
        }

        $progress->save();

        return $progress->load(['sign', 'level']);
    }

    /**
     * Manually update progress (used by user-facing endpoint).
     */
    public function updateProgress(int $userId, array $data): Progress
    {
        $progress = Progress::firstOrNew([
            'user_id' => $userId,
            'sign_id' => $data['sign_id'],
        ]);

        $progress->level_id = $data['level_id'];

        if (isset($data['is_completed'])) {
            $progress->is_completed = $data['is_completed'];
        }

        if (isset($data['best_confidence'])) {
            // Keep the higher value
            if ((float) $data['best_confidence'] > (float) ($progress->best_confidence ?? 0)) {
                $progress->best_confidence = $data['best_confidence'];
            }
        }

        $progress->save();

        return $progress->load(['sign', 'level']);
    }

    /**
     * Mark a sign as completed manually.
     */
    public function markCompleted(int $userId, int $signId): ?Progress
    {
        $progress = Progress::where('user_id', $userId)
                            ->where('sign_id', $signId)
                            ->first();

        if (!$progress) {
            return null;
        }

        $progress->is_completed = true;
        $progress->save();

        return $progress->load(['sign', 'level']);
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