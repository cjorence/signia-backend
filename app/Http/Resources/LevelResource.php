<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'difficulty'  => $this->difficulty ?? 'easy',
            'order'       => $this->order,
            'required_xp' => $this->required_xp,
            'mode_level'  => $this->mode_level ?? 'alphabet',
            'mode'        => $this->mode_level ?? 'alphabet',
            'signs_count' => $this->whenCounted('signs'),
            'quests_count'=> $this->whenCounted('quests'),
            'signs'       => SignResource::collection($this->whenLoaded('signs')),
            'quests'      => QuestResource::collection($this->whenLoaded('quests')),
            'is_archived' => !is_null($this->deleted_at),
            'deleted_at'  => $this->deleted_at?->toISOString(),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}