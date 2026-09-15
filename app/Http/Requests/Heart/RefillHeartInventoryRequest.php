<?php

namespace App\Http\Requests\Heart;

use Illuminate\Foundation\Http\FormRequest;

class RefillHeartInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
