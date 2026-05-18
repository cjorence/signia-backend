<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'sign_id'         => $this->sign_id,
            'level_id'        => $this->level_id,
            'is_completed'    => $this->is_completed,
            'attempts'        => $this->attempts,
            'best_confidence' => $this->best_confidence,
            'sign'            => new SignResource($this->whenLoaded('sign')),
            'level'           => new LevelResource($this->whenLoaded('level')),
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}