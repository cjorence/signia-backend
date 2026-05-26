<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string'],
            'question_type' => ['required', Rule::in(['mcq', 'identification', 'gesture'])],
            'sign_id' => ['nullable', 'integer', 'exists:signs,id'],
            'correct_answer' => ['required', 'string', 'max:255'],
        ];
    }
}
