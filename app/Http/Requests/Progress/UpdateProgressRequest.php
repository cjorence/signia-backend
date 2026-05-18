<?php

namespace App\Http\Requests\Progress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sign_id'         => ['required', 'integer', 'exists:signs,id'],
            'level_id'        => ['required', 'integer', 'exists:levels,id'],
            'is_completed'    => ['sometimes', 'boolean'],
            'best_confidence' => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'sign_id.required'  => 'Sign ID is required.',
            'sign_id.exists'    => 'The selected sign does not exist.',
            'level_id.required' => 'Level ID is required.',
            'level_id.exists'   => 'The selected level does not exist.',
        ];
    }
}