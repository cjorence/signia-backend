<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...\App\Support\LegalAgreement::rules(),
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'code'     => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Name is required.',
            'terms_accepted.required' => 'Please agree to the Terms of Service.',
            'terms_accepted.accepted' => 'Please agree to the Terms of Service.',
            'privacy_acknowledged.required' => 'Please acknowledge the Privacy Notice.',
            'privacy_acknowledged.accepted' => 'Please acknowledge the Privacy Notice.',
            'terms_version.in' => 'The terms have changed. Refresh this page and review the current version.',
            'privacy_version.in' => 'The privacy notice has changed. Refresh this page and review the current version.',
            'email.required'     => 'Email is required.',
            'email.email'        => 'Please provide a valid email address.',
            'email.unique'       => 'This email is already registered.',
            'password.required'  => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
