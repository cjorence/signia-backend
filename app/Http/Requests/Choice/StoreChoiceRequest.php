<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'choice_text' => ['required', 'string', 'max:255'],
            'is_correct' => ['sometimes', 'boolean'],
        ];
    }
}
