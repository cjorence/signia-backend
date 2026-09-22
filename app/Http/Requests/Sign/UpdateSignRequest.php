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
            'model_label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'video'       => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:51200'],
            'image_url'   => ['nullable', 'string', 'max:2048'],
            'video_url'   => ['nullable', 'string', 'max:2048'],
            'video_type'  => ['sometimes', 'nullable', Rule::in(['local', 'youtube'])],
            'video_start' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^\d+:\d{2}$/'],
            'video_end'   => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^\d+:\d{2}$/'],
            'difficulty'  => ['sometimes', 'required', Rule::in(['easy', 'medium', 'hard'])],
            'xp_reward'   => ['sometimes', 'required', 'integer', 'min:0'],
            'sort_order'  => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
