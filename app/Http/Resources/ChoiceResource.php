<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdminRoute = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'choice_text' => $this->choice_text,
            'is_correct' => $this->when($isAdminRoute, $this->is_correct),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
