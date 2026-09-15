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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $signId = $this->input('sign_id');
            $levelId = $this->input('level_id');

            if ($signId && $levelId) {
                $belongs = \App\Models\Sign::where('id', $signId)
                    ->where('level_id', $levelId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('sign_id', 'The selected sign does not belong to the specified level.');
                }
            }
        });
    }
}
