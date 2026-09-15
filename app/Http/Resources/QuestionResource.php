<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdminRoute = $request->is('api/admin/*');
        $choices = $this->whenLoaded('choices', function () use ($isAdminRoute) {
            if ($isAdminRoute || $this->question_type !== 'mcq') {
                return $this->choices;
            }

            if ($this->sign_id && $this->getAttribute('lesson_options') !== null) {
                return collect();
            }

            $correctChoice = $this->choices->firstWhere('is_correct', true);
            $distractors = $this->choices
                ->where('is_correct', false)
                ->shuffle()
                ->take(3);

            return $correctChoice
                ? collect([$correctChoice])->merge($distractors)
                : $distractors;
        });

        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'sign_id' => $this->sign_id,
            'correct_answer' => $this->when($isAdminRoute, $this->correct_answer),
            'choices' => ChoiceResource::collection($choices),
            'lesson_options' => $this->when(
                ! $isAdminRoute && $this->getAttribute('lesson_options') !== null,
                $this->getAttribute('lesson_options')
            ),
            'sign' => new SignResource($this->whenLoaded('sign')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
