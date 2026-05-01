<?php

namespace App\Services;

use App\Models\UserQuest;
use Illuminate\Database\Eloquent\Collection;

class UserQuestService
{
    /**
     * Get all quests belonging to the given user.
     */
    public function getUserQuests(int $userId): Collection
    {
        return UserQuest::where('user_id', $userId)
                        ->with('quest.level')
                        ->orderBy('created_at', 'desc')
                        ->get();
    }

    /**
     * Create or update the user's quest status.
     * Returns the user_quest record and a context-aware message.
     */
    public function updateQuestStatus(int $userId, array $data): array
    {
        $userQuest = UserQuest::updateOrCreate(
            [
                'user_id'  => $userId,
                'quest_id' => $data['quest_id'],
            ],
            [
                'status' => $data['status'],
            ]
        );

        $userQuest->load('quest.level');

        $message = $data['status'] === 'completed'
            ? 'Quest completed!'
            : 'Quest status updated.';

        return [
            'user_quest' => $userQuest,
            'message'    => $message,
        ];
    }
}