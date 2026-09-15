<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'answer' => ['nullable', 'required_without:answer_sign_id'],
            'answer_sign_id' => ['nullable', 'integer', 'required_without:answer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('answer') && $this->filled('answer_sign_id')) {
                $validator->errors()->add('answer', 'Submit either answer or answer_sign_id, not both.');
            }
        });
    }
}
