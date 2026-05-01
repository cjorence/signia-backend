<?php

namespace App\Services;

use App\Models\Level;
use Illuminate\Database\Eloquent\Collection;

class LevelService
{
    /**
     * Get all levels ordered by 'order' with relation counts.
     */
    public function getAllLevels(): Collection
    {
        return Level::withCount(['signs', 'quests'])
                    ->orderBy('order')
                    ->get();
    }

    /**
     * Load full level detail with signs and quests.
     */
    public function getLevelDetail(Level $level): Level
    {
        $level->load(['signs', 'quests']);
        $level->loadCount(['signs', 'quests']);

        return $level;
    }

    /**
     * Create a new level.
     */
    public function createLevel(array $data): Level
    {
        return Level::create($data);
    }

    /**
     * Update an existing level.
     */
    public function updateLevel(Level $level, array $data): Level
    {
        $level->update($data);

        return $level->fresh();
    }

    /**
     * Delete a level.
     */
    public function deleteLevel(Level $level): bool
    {
        return (bool) $level->delete();
    }
}