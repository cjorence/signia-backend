<?php

namespace App\Http\Requests\UserQuest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserQuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Any authenticated user
    }

    public function rules(): array
    {
        return [
            'quest_id' => ['required', 'integer', 'exists:quests,id'],
            'status'   => ['required', Rule::in(['pending', 'completed'])],
        ];
    }
}