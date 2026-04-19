<?php

namespace App\Http\Requests\Sign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'level_id'    => ['sometimes', 'required', 'integer', 'exists:levels,id'],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'fsl_name'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url'   => ['nullable', 'string', 'max:2048'],
            'video_url'   => ['nullable', 'string', 'max:2048'],
            'model_label' => ['sometimes', 'required', 'string', 'max:255'],
            'difficulty'  => ['sometimes', 'required', Rule::in(['easy', 'medium', 'hard'])],
            'xp_reward'   => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}