<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'level_id'    => $this->level_id,
            'name'        => $this->name,
            'fsl_name'    => $this->fsl_name,
            'description' => $this->description,
            'image_url'   => $this->formatMediaUrl($this->image_url),
            'video_url'   => $this->video_type === 'youtube'
                                ? $this->video_url
                                : $this->formatMediaUrl($this->video_url),
            'video_type'  => $this->video_type ?? 'local',
            'video_start' => $this->video_start,
            'video_end'   => $this->video_end,
            'difficulty'  => $this->difficulty,
            'xp_reward'   => $this->xp_reward,
            'sort_order'  => $this->sort_order,
            'level'       => new LevelResource($this->whenLoaded('level')),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }

    protected function formatMediaUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $cleanPath = ltrim($url, '/');
        if (!str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = 'storage/' . $cleanPath;
        }

        return asset($cleanPath);
    }
}
