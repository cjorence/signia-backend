<?php

namespace App\Services;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Sign;
use Illuminate\Database\Eloquent\Collection;

class SignService
{
    /**
     * Get all signs across all levels or filtered by level.
     */
    public function getAllSigns(?int $levelId = null): Collection
    {
        $query = Sign::with('level')
                     ->orderBy('level_id')
                     ->orderBy('sort_order')
                     ->orderBy('id');

        if ($levelId) {
            $query->where('level_id', $levelId);
        }

        return $query->get();
    }

    /**
     * Get all signs for a specific level.
     */
    public function getSignsByLevel(Level $level): Collection
    {
        return $level->signs()
                     ->orderBy('sort_order')
                     ->orderBy('id')
                     ->get();
    }

    /**
     * Get a single sign with its level loaded.
     */
    public function getSignDetail(Sign $sign): Sign
    {
        return $sign->load('level');
    }

    /**
     * Create a new sign and ensure a quiz question is available for it.
     */
    public function createSign(array $data): Sign
    {
        if (!isset($data['sort_order'])) {
            $maxOrder = Sign::where('level_id', $data['level_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        $sign = Sign::create($data);

        $level = Level::find($sign->level_id);
        if ($level) {
            app(QuizService::class)->ensureQuestionsForLevelSigns($level);
        }

        return $sign->load('level');
    }

    /**
     * Update an existing sign.
     */
    public function updateSign(Sign $sign, array $data): Sign
    {
        $sign->update($data);

        $letter = $sign->fsl_name ?: $sign->name;
        Question::where('sign_id', $sign->id)->update(['correct_answer' => $letter]);
        $questionIds = Question::where('sign_id', $sign->id)->pluck('id');
        Choice::whereIn('question_id', $questionIds)->where('is_correct', true)->update(['choice_text' => $letter]);

        return $sign->fresh()->load('level');
    }

    /**
     * Delete a sign.
     */
    public function deleteSign(Sign $sign): bool
    {
        return (bool) $sign->delete();
    }

    /**
     * Batch update sign sort orders atomically.
     */
    public function reorderSigns(array $items): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Sign::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });
    }
}
