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
        if (!isset($data['order'])) {
            $maxOrder = Level::max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }

        if (!isset($data['required_xp'])) {
            $data['required_xp'] = 0;
        }

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
     * Get all archived levels.
     */
    public function getArchivedLevels(): Collection
    {
        return Level::onlyTrashed()
                    ->withCount(['signs', 'quests'])
                    ->orderBy('order')
                    ->get();
    }

    /**
     * Archive a level (soft delete).
     */
    public function archiveLevel(Level $level): bool
    {
        return (bool) $level->delete();
    }

    /**
     * Restore an archived level.
     */
    public function restoreLevel(int $levelId): Level
    {
        $level = Level::onlyTrashed()->findOrFail($levelId);
        $level->restore();

        return $level->fresh()->loadCount(['signs', 'quests']);
    }

    /**
     * Permanently delete a level if no child data is attached.
     */
    public function forceDeleteLevel(int $levelId): bool
    {
        $level = Level::withTrashed()->findOrFail($levelId);

        // Safety check: ensure no lessons or player records exist
        if (\App\Models\Sign::where('level_id', $levelId)->exists() ||
            \App\Models\Progress::where('level_id', $levelId)->exists() ||
            \App\Models\GestureLog::where('level_id', $levelId)->exists() ||
            \App\Models\Quiz::where('level_id', $levelId)->exists() ||
            \App\Models\Quest::where('level_id', $levelId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'level' => 'This category cannot be permanently deleted because lessons, quizzes, or student records are attached to it. Please keep it archived instead.',
            ]);
        }

        return (bool) $level->forceDelete();
    }

    /**
     * Delete a level (permanent if no child records exist).
     */
    public function deleteLevel(Level $level): bool
    {
        return $this->forceDeleteLevel($level->id);
    }

    /**
     * Batch update level orders atomically.
     */
    public function reorderLevels(array $items): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Level::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });
    }
}