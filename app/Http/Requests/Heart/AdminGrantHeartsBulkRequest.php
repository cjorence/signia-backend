<?php

namespace App\Http\Requests\Heart;

use Illuminate\Foundation\Http\FormRequest;

class AdminGrantHeartsBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'grant_all'   => ['sometimes', 'boolean'],
            'user_ids'    => ['sometimes', 'array'],
            'user_ids.*'  => ['integer', 'exists:users,id'],
            'amount'      => ['required', 'integer', 'min:1', 'max:100'],
            'reason'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $grantAll = filter_var($this->input('grant_all'), FILTER_VALIDATE_BOOLEAN);
            $userIds = $this->input('user_ids');
            if (! $grantAll && (empty($userIds) || ! is_array($userIds) || count($userIds) === 0)) {
                $validator->errors()->add('user_ids', 'Please select at least one player to grant hearts.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Please select at least one player to grant hearts.',
            'amount.required'   => 'Please provide the number of hearts to grant.',
            'amount.min'        => 'Hearts to grant must be at least 1.',
            'amount.max'        => 'Hearts to grant cannot exceed 100.',
        ];
    }
}
