<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'achievement_id' => $this->achievement_id,
            'achievement' => new AchievementResource($this->whenLoaded('achievement')),
            'unlocked_at' => $this->unlocked_at?->toISOString(),
        ];
    }
}