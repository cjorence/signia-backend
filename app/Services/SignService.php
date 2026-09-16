<?php

namespace App\Services;

use App\Models\Level;
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
     * Create a new sign.
     */
    public function createSign(array $data): Sign
    {
        if (!isset($data['sort_order'])) {
            $maxOrder = Sign::where('level_id', $data['level_id'])->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }

        $data = $this->handleFileUploads($data);

        $sign = Sign::create($data);

        return $sign->load('level');
    }

    /**
     * Update an existing sign.
     */
    public function updateSign(Sign $sign, array $data): Sign
    {
        $data = $this->handleFileUploads($data, $sign);

        $sign->update($data);

        return $sign->fresh()->load('level');
    }

    /**
     * Delete a sign.
     */
    public function deleteSign(Sign $sign): bool
    {
        if ($sign->image_url && Storage::disk('public')->exists($sign->image_url)) {
            Storage::disk('public')->delete($sign->image_url);
        }

        if ($sign->video_url && Storage::disk('public')->exists($sign->video_url)) {
            Storage::disk('public')->delete($sign->video_url);
        }

        return (bool) $sign->delete();
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
}
