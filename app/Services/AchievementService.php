<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\GestureLog;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserQuest;
use Illuminate\Database\Eloquent\Collection;

class AchievementService
{
    public function getAllAchievements(): Collection
    {
        return Achievement::orderBy('id')->get();
    }

    public function getUserAchievements(int $userId): Collection
    {
        return UserAchievement::with('achievement')
            ->where('user_id', $userId)
            ->orderByDesc('unlocked_at')
            ->get();
    }

    public function checkAndUnlock(User $user): Collection
    {
        $user->load('playerProfile');

        $achievements = Achievement::all();
        $unlocked = collect();

        foreach ($achievements as $achievement) {
            if ($this->alreadyUnlocked($user->id, $achievement->id)) {
                continue;
            }

            if ($this->conditionMet($user, $achievement)) {
                $unlocked->push($this->assignAchievement($user->id, $achievement->id));
            }
        }

        return new Collection($unlocked);
    }

    public function assignAchievement(int $userId, int $achievementId): UserAchievement
    {
        return UserAchievement::firstOrCreate(
            [
                'user_id' => $userId,
                'achievement_id' => $achievementId,
            ],
            [
                'unlocked_at' => now(),
            ]
        )->load('achievement');
    }

    protected function alreadyUnlocked(int $userId, int $achievementId): bool
    {
        return UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievementId)
            ->exists();
    }

    protected function conditionMet(User $user, Achievement $achievement): bool
    {
        return match ($achievement->condition_type) {
            'total_xp' => $this->totalXpReached($user, $achievement->condition_value),
            'completed_signs' => $this->completedSignsReached($user->id, $achievement->condition_value),
            'completed_quests' => $this->completedQuestsReached($user->id, $achievement->condition_value),
            'quiz_score' => $this->quizScoreReached($user->id, $achievement->condition_value),
            'gesture_accuracy' => $this->gestureAccuracyReached($user->id, $achievement->condition_value),
            'streak' => $this->streakReached($user, $achievement->condition_value),
            default => false,
        };
    }

    protected function totalXpReached(User $user, int $value): bool
    {
        return ($user->playerProfile?->total_xp ?? 0) >= $value;
    }

    protected function completedSignsReached(int $userId, int $value): bool
    {
        return Progress::where('user_id', $userId)
            ->where('is_completed', true)
            ->count() >= $value;
    }

    protected function completedQuestsReached(int $userId, int $value): bool
    {
        return UserQuest::where('user_id', $userId)
            ->where('status', 'completed')
            ->count() >= $value;
    }

    protected function quizScoreReached(int $userId, int $value): bool
    {
        return QuizAttempt::where('user_id', $userId)
            ->where('score', '>=', $value)
            ->exists();
    }

    protected function gestureAccuracyReached(int $userId, int $value): bool
    {
        $total = GestureLog::where('user_id', $userId)->count();

        if ($total === 0) {
            return false;
        }

        $correct = GestureLog::where('user_id', $userId)
            ->where('is_correct', true)
            ->count();

        $accuracy = round(($correct / $total) * 100, 2);

        return $accuracy >= $value;
    }

    protected function streakReached(User $user, int $value): bool
    {
        return ($user->playerProfile?->streak ?? 0) >= $value;
    }
}