<?php

namespace App\Services;

use App\Models\User;
use App\Support\LegalAgreement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthService
{
    /**
     * Register a new user and create their player profile.
     */
    public function register(array $data): array
    {
        Validator::make($data, LegalAgreement::rules())->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'last_active_at' => now(),
                'is_active' => true,
            ]);
            // Verification is server-controlled and intentionally not mass assignable.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->playerProfile()->create([
                'current_level' => 1,
                'total_xp' => 0,
                'streak' => 0,
            ]);

            LegalAgreement::record($user, 'email');
            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load('playerProfile');

            try {
                app(AdminNotificationService::class)->notifyNewUser($user);
            } catch (\Throwable $e) {
                // Non-blocking notification dispatch
            }

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Authenticate a user and issue a token.
     * Returns: ['status' => 'success'|'invalid'|'inactive', 'user' => ?, 'token' => ?]
     */
    public function login(array $credentials): array
    {
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            return ['status' => 'invalid'];
        }

        if (empty($user->password)) {
            return ['status' => 'google_only'];
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return ['status' => 'invalid'];
        }

        if (! $user->is_active) {
            return ['status' => 'inactive'];
        }

        $user->update(['last_active_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('playerProfile');

        return [
            'status' => 'success',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Authenticate or register a user via Google OAuth data.
     */
    public function handleGoogleAuth(array $googleData, array $agreement = []): array
    {
        return DB::transaction(function () use ($googleData, $agreement) {
            $googleId = $googleData['id'] ?? $googleData['sub'] ?? null;
            $email = strtolower(trim($googleData['email'] ?? ''));
            $name = trim($googleData['name'] ?? '') ?: (explode('@', $email)[0] ?: 'User');
            $avatar = $googleData['avatar'] ?? $googleData['picture'] ?? null;

            /** @var User|null $user */
            $user = $googleId ? User::where('google_id', $googleId)->first() : null;
            if (!$user) {
                $user = User::where('email', $email)->first();
                // An email match must not replace an already-linked Google identity.
                // Non-Google-hosted addresses need separate account ownership verification.
                $googleOwnsEmail = str_ends_with($email, '@gmail.com') || !empty($googleData['hd']);
                if ($user && (!empty($user->google_id) || !$googleOwnsEmail)) {
                    return ['status' => 'linking_required'];
                }
            }

            if ($user) {
                if (! $user->is_active) {
                    return ['status' => 'inactive'];
                }

                // Link Google ID if not already linked
                if ($googleId && empty($user->google_id)) {
                    $user->google_id = $googleId;
                }

                // Mark email as verified if not verified yet
                if (empty($user->email_verified_at)) {
                    $user->email_verified_at = now();
                }

                // Update user name if current name is empty
                if (empty($user->name) && ! empty($name)) {
                    $user->name = $name;
                }

                $user->last_active_at = now();
                $user->save();

                // Ensure player profile exists
                if (! $user->playerProfile) {
                    $user->playerProfile()->create([
                        'current_level' => 1,
                        'total_xp' => 0,
                        'streak' => 0,
                        'avatar' => $avatar,
                    ]);
                } elseif ($avatar && empty($user->playerProfile->avatar)) {
                    $user->playerProfile->update(['avatar' => $avatar]);
                }
            } else {
                // Google verifies identity; it does not accept Signia's agreement.
                // Do not create a user, player profile, or session until explicit acceptance.
                if (Validator::make($agreement, LegalAgreement::rules())->fails()) {
                    return ['status' => 'agreement_required'];
                }

                // Create brand new user via Google
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'last_active_at' => now(),
                    'is_active' => true,
                    'role' => 'user',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $user->playerProfile()->create([
                    'current_level' => 1,
                    'total_xp' => 0,
                    'streak' => 0,
                    'avatar' => $avatar,
                ]);
                LegalAgreement::record($user, 'google');

                try {
                    app(AdminNotificationService::class)->notifyNewUser($user);
                } catch (\Throwable $e) {
                    // Non-blocking notification dispatch
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            $user->load('playerProfile');

            return [
                'status' => 'success',
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Load and return user with player profile.
     */
    public function me(User $user): User
    {
        return $user
            ->load('playerProfile')
            ->loadCount([
                'progress as signs_learned_count' => fn ($query) => $query->where('is_completed', true),
                'storyProgress as stories_done_count' => fn ($query) => $query->where('status', 'completed'),
            ]);
    }
}
