<?php

namespace App\Http\Requests\Gesture;

use Illuminate\Foundation\Http\FormRequest;

class StoreGestureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sign_id'          => ['required', 'integer', 'exists:signs,id'],
            'level_id'         => ['required', 'integer', 'exists:levels,id'],
            'expected_sign'    => ['required', 'string', 'max:255'],
            'predicted_sign'   => ['required', 'string', 'max:255'],
            'confidence'       => ['required', 'numeric', 'between:0,100'],
            'attempt_duration' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sign_id.required'         => 'Sign ID is required.',
            'sign_id.exists'           => 'The selected sign does not exist.',
            'level_id.required'        => 'Level ID is required.',
            'level_id.exists'          => 'The selected level does not exist.',
            'expected_sign.required'   => 'Expected sign label is required.',
            'predicted_sign.required'  => 'Predicted sign label is required.',
            'confidence.required'      => 'Confidence score is required.',
            'confidence.between'       => 'Confidence must be between 0 and 100.',
        ];
    }
}