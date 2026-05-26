<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdminRoute = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'sign_id' => $this->sign_id,
            'correct_answer' => $this->when($isAdminRoute, $this->correct_answer),
            'choices' => ChoiceResource::collection($this->whenLoaded('choices')),
            'sign' => new SignResource($this->whenLoaded('sign')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
