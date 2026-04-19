<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'avatar'           => $this->avatar,
            'current_level'    => $this->current_level,
            'total_xp'         => $this->total_xp,
            'streak'           => $this->streak,
            'last_played_date' => $this->last_played_date?->toDateString(),
        ];
    }
}