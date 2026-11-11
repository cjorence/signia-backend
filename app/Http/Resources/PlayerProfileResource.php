<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalXp = max(0, (int) ($this->total_xp ?? 0));
        $calculatedLevel = intdiv($totalXp, 100) + 1;

        return [
            'avatar'           => $this->avatar,
            'current_level'    => $calculatedLevel,
            'total_xp'         => $this->total_xp,
            'streak'           => $this->active_streak,
            'last_played_date' => $this->last_played_date?->toDateString(),
            'hearts' => $this->hearts,
            'max_hearts' => $this->max_hearts,
            'heart_inventory' => $this->heart_inventory,
            'next_heart_at' => $this->next_heart_at?->toISOString(),
        ];
    }
}
