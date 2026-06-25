<?php

namespace App\Http\Requests\Heart;

use Illuminate\Foundation\Http\FormRequest;

class AdminGrantHeartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}