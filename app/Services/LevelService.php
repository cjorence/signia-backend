<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;

class LevelService
{
    /**
     * Get all levels ordered by 'order' with relation counts.
     */
    public function getAllLevels(): Collection
    {
        return Level::withCount(['signs', 'quests'])
                    ->with(['signs' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                    ->orderByRaw("CASE difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 WHEN 'hard' THEN 3 ELSE 4 END ASC")
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

        if (array_key_exists('question_prompt', $data) || array_key_exists('name', $data)) {
            $prompt = app(QuizService::class)->resolveQuestionPromptForLevel($level);
            $quizIds = $level->quizzes()->pluck('id');
            if ($quizIds->isNotEmpty()) {
                Question::whereIn('quiz_id', $quizIds)
                    ->whereNotNull('sign_id')
                    ->update(['question_text' => $prompt]);
            }
        }

        return $level->fresh();
    }

    /**
     * Get all archived levels.
     */
    public function getArchivedLevels(): Collection
    {
        return Level::onlyTrashed()
                    ->withCount(['signs', 'quests'])
                    ->orderByRaw("CASE difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 WHEN 'hard' THEN 3 ELSE 4 END ASC")
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

        // Safety check 1: ensure no lessons (active or archived) exist under this category
        if (\App\Models\Sign::withTrashed()->where('level_id', $levelId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'level' => 'This category cannot be permanently deleted because it still contains lessons (active or archived). Please delete or reassign all lessons before deleting the category.',
            ]);
        }

        // Safety check 2: ensure no student learning records exist for this category
        if (\App\Models\Progress::where('level_id', $levelId)->exists() ||
            \App\Models\GestureLog::where('level_id', $levelId)->exists() ||
            \App\Models\Score::where('level_id', $levelId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'level' => 'This category cannot be permanently deleted because student learning records (progress, camera practice logs, or scores) are attached to it. Please keep it archived instead to protect student history.',
            ]);
        }

        // Safety check 3: ensure no active quests belong to this category
        if (\App\Models\Quest::where('level_id', $levelId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'level' => 'This category cannot be permanently deleted because quests are attached to it. Please remove or reassign the quests first.',
            ]);
        }

        // Safety check 4: quizzes and quiz attempts
        $quizzes = \App\Models\Quiz::where('level_id', $levelId)->get();
        if ($quizzes->isNotEmpty()) {
            $quizIds = $quizzes->pluck('id');

            // Block if students have any quiz attempts or scores
            if (\App\Models\QuizAttempt::whereIn('quiz_id', $quizIds)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'level' => 'This category cannot be permanently deleted because student quiz attempts and scores are attached to it. Please keep it archived instead.',
                ]);
            }

            // Block if quizzes contain custom questions
            if (\App\Models\Question::whereIn('quiz_id', $quizIds)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'level' => 'This category cannot be permanently deleted because quiz questions are attached to it. Please remove the questions first or keep it archived.',
                ]);
            }
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($level, $levelId) {
            // Clean up any empty/auto-generated quizzes with 0 questions & 0 attempts
            \App\Models\Quiz::where('level_id', $levelId)->delete();

            return (bool) $level->forceDelete();
        });
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