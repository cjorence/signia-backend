<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'quiz_id' => $this->quiz_id,
            'score' => $this->score,
            'completed_at' => $this->completed_at?->toISOString(),
            'quiz' => new QuizResource($this->whenLoaded('quiz')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
