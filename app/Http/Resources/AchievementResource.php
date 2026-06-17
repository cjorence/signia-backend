<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'condition_type' => $this->condition_type,
            'condition_value' => $this->condition_value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}