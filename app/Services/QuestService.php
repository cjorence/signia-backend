<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Quest;
use Illuminate\Database\Eloquent\Collection;

class QuestService
{
    /**
     * Get all quests for a specific level.
     */
    public function getQuestsByLevel(Level $level): Collection
    {
        return $level->quests()->orderBy('id')->get();
    }

    /**
     * Get a single quest with its level loaded.
     */
    public function getQuestDetail(Quest $quest): Quest
    {
        return $quest->load('level');
    }

    /**
     * Create a new quest.
     */
    public function createQuest(array $data): Quest
    {
        $quest = Quest::create($data);

        return $quest->load('level');
    }

    /**
     * Update an existing quest.
     */
    public function updateQuest(Quest $quest, array $data): Quest
    {
        $quest->update($data);

        return $quest->fresh()->load('level');
    }

    /**
     * Delete a quest.
     */
    public function deleteQuest(Quest $quest): bool
    {
        return (bool) $quest->delete();
    }
}