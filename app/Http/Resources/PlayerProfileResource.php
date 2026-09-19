<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $streak = (int) ($this->streak ?? 0);
        if ($this->last_played_date) {
            $lastPlayed = $this->last_played_date->copy()->startOfDay();
            if (! $lastPlayed->isToday() && ! $lastPlayed->isYesterday()) {
                $streak = 0;
            }
        } else {
            $streak = 0;
        }

        return [
            'avatar'           => $this->avatar,
            'current_level'    => $this->current_level,
            'total_xp'         => $this->total_xp,
            'streak'           => $streak,
            'last_played_date' => $this->last_played_date?->toDateString(),
            'hearts' => $this->hearts,
            'max_hearts' => $this->max_hearts,
            'heart_inventory' => $this->heart_inventory,
            'next_heart_at' => $this->next_heart_at?->toISOString(),
        ];
    }
}
