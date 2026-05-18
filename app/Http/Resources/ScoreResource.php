<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'level_id'   => $this->level_id,
            'score'      => $this->score,
            'time_taken' => $this->time_taken,
            'level'      => new LevelResource($this->whenLoaded('level')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}