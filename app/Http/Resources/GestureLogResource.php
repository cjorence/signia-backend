<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GestureLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'sign_id'          => $this->sign_id,
            'level_id'         => $this->level_id,
            'expected_sign'    => $this->expected_sign,
            'predicted_sign'   => $this->predicted_sign,
            'confidence'       => $this->confidence,
            'is_correct'       => $this->is_correct,
            'attempt_duration' => $this->attempt_duration,
            'sign'             => new SignResource($this->whenLoaded('sign')),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}