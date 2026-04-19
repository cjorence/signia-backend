<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'level_id'    => $this->level_id,
            'name'        => $this->name,
            'fsl_name'    => $this->fsl_name,
            'description' => $this->description,
            'image_url'   => $this->image_url,
            'video_url'   => $this->video_url,
            'model_label' => $this->model_label,
            'difficulty'  => $this->difficulty,
            'xp_reward'   => $this->xp_reward,
            'level'       => new LevelResource($this->whenLoaded('level')),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}