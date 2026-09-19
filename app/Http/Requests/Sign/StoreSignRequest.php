<?php

namespace App\Http\Requests\Sign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'level_id'    => ['required', 'integer', 'exists:levels,id'],
            'name'        => ['required', 'string', 'max:255'],
            'fsl_name'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
            'video'       => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:51200'],
            'image_url'   => ['nullable', 'string', 'max:2048'],
            'video_url'   => ['nullable', 'string', 'max:2048'],
            'video_type'  => ['nullable', Rule::in(['local', 'youtube'])],
            'video_start' => ['nullable', 'string', 'max:10', 'regex:/^\d+:\d{2}$/'],
            'video_end'   => ['nullable', 'string', 'max:10', 'regex:/^\d+:\d{2}$/'],
            'difficulty'  => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'xp_reward'   => ['required', 'integer', 'min:0'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }
}
