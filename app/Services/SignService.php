<?php

namespace App\Services;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Sign;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SignService
{
    /**
     * Get all signs across all levels or filtered by level.
     */
    public function getAllSigns(?int $levelId = null): Collection
    {
        $query = Sign::with('level')
                     ->select('signs.*')
                     ->leftJoin('levels', 'levels.id', '=', 'signs.level_id')
                     ->orderByRaw("CASE levels.difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 WHEN 'hard' THEN 3 ELSE 4 END ASC")
                     ->orderBy('levels.order')
                     ->orderBy('signs.sort_order')
                     ->orderBy('signs.id');

        if ($levelId) {
            $query->where('signs.level_id', $levelId);
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

        $data = $this->handleFileUploads($data);

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
        $data = $this->handleFileUploads($data, $sign);

        $sign->update($data);

        $letter = $sign->fsl_name ?: $sign->name;
        Question::where('sign_id', $sign->id)->update(['correct_answer' => $letter]);
        $questionIds = Question::where('sign_id', $sign->id)->pluck('id');
        Choice::whereIn('question_id', $questionIds)->where('is_correct', true)->update(['choice_text' => $letter]);

        return $sign->fresh()->load('level');
    }

    /**
     * Get all archived (soft-deleted) signs.
     */
    public function getArchivedSigns(?int $levelId = null): Collection
    {
        $query = Sign::onlyTrashed()
                     ->with('level')
                     ->select('signs.*')
                     ->leftJoin('levels', 'levels.id', '=', 'signs.level_id')
                     ->orderByRaw("CASE levels.difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 WHEN 'hard' THEN 3 ELSE 4 END ASC")
                     ->orderBy('levels.order')
                     ->orderBy('signs.sort_order')
                     ->orderBy('signs.id');

        if ($levelId) {
            $query->where('signs.level_id', $levelId);
        }

        return $query->get();
    }

    /**
     * Archive a sign (soft delete, preserves media files for restoration).
     */
    public function archiveSign(Sign $sign): bool
    {
        return (bool) $sign->delete();
    }

    /**
     * Restore an archived sign.
     */
    public function restoreSign(int $signId): Sign
    {
        $sign = Sign::onlyTrashed()->findOrFail($signId);
        $sign->restore();

        return $sign->fresh()->load('level');
    }

    /**
     * Permanently delete a sign and its media files.
     */
    public function forceDeleteSign(int $signId): bool
    {
        $sign = Sign::withTrashed()->findOrFail($signId);

        // Safety check: ensure no student progress or logs are tied to this sign
        if (\App\Models\Progress::where('sign_id', $signId)->exists() ||
            \App\Models\GestureLog::where('sign_id', $signId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sign' => 'This lesson cannot be permanently deleted because student progress or attempt history is attached to it. Please keep it archived instead.',
            ]);
        }

        if ($sign->image_url && Storage::disk('public')->exists($sign->image_url)) {
            Storage::disk('public')->delete($sign->image_url);
        }

        if ($sign->video_url && Storage::disk('public')->exists($sign->video_url)) {
            Storage::disk('public')->delete($sign->video_url);
        }

        return (bool) $sign->forceDelete();
    }

    /**
     * Delete a sign (permanent if no student records exist, or forceDelete).
     */
    public function deleteSign(Sign $sign): bool
    {
        return $this->forceDeleteSign($sign->id);
    }

    /**
     * Process image and video uploads if provided, returning processed data array.
     */
    protected function handleFileUploads(array $data, ?Sign $existingSign = null): array
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($existingSign?->image_url && Storage::disk('public')->exists($existingSign->image_url)) {
                Storage::disk('public')->delete($existingSign->image_url);
            }
            $data['image_url'] = $data['image']->store('signs/images', 'public');
        }
        unset($data['image']);

        if (isset($data['video']) && $data['video'] instanceof UploadedFile) {
            if ($existingSign?->video_url && Storage::disk('public')->exists($existingSign->video_url)) {
                Storage::disk('public')->delete($existingSign->video_url);
            }
            $data['video_url'] = $data['video']->store('signs/videos', 'public');
        }
        unset($data['video']);

        return $data;
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
