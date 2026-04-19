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
            'order'       => $this->order,
            'required_xp' => $this->required_xp,
            'signs_count' => $this->whenCounted('signs'),
            'quests_count'=> $this->whenCounted('quests'),
            'signs'       => SignResource::collection($this->whenLoaded('signs')),
            'quests'      => QuestResource::collection($this->whenLoaded('quests')),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}