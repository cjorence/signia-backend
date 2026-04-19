<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'level_id'    => $this->level_id,
            'name'        => $this->name,
            'description' => $this->description,
            'level'       => new LevelResource($this->whenLoaded('level')),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}