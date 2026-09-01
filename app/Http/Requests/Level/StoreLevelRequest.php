<?php

namespace App\Http\Requests\Level;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'difficulty'  => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'order'       => ['nullable', 'integer', 'min:0'],
            'required_xp' => ['nullable', 'integer', 'min:0'],
        ];
    }
}