<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class LegalAgreement
{
    // Keep released document versions immutable and in sync with frontend legalDocuments.ts.
    public const TERMS_VERSION = '2026-10-18';

    public const PRIVACY_VERSION = '2026-10-19.1';

    public static function rules(): array
    {
        return [
            'terms_accepted' => ['required', 'accepted'],
            'privacy_acknowledged' => ['required', 'accepted'],
            'terms_version' => ['required', Rule::in([self::TERMS_VERSION])],
            'privacy_version' => ['required', Rule::in([self::PRIVACY_VERSION])],
        ];
    }

    public static function record(User $user, string $method): void
    {
        DB::table('legal_acceptances')->insert([
            'user_id' => $user->id,
            'terms_version' => self::TERMS_VERSION,
            'privacy_version' => self::PRIVACY_VERSION,
            'accepted_at' => now(),
            'method' => $method,
        ]);
    }
}
