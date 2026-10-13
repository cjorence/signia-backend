<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Register a new user and create their player profile.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'email_verified_at' => now(),
            'last_active_at'    => now(),
            'is_active'         => true,
        ]);

        $user->playerProfile()->create([
            'current_level' => 1,
            'total_xp'      => 0,
            'streak'        => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('playerProfile');

        return [
            'user'  => $user,
            'token' => $token,
        ];
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

        if (!$user->is_active) {
            return ['status' => 'inactive'];
        }

        $user->update(['last_active_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('playerProfile');

        return [
            'status' => 'success',
            'user'   => $user,
            'token'  => $token,
        ];
    }

    /**
     * Authenticate or register a user via Google OAuth data.
     */
    public function handleGoogleAuth(array $googleData): array
    {
        $googleId = $googleData['id'] ?? $googleData['sub'] ?? null;
        $email = strtolower(trim($googleData['email'] ?? ''));
        $name = trim($googleData['name'] ?? '') ?: (explode('@', $email)[0] ?: 'User');
        $avatar = $googleData['avatar'] ?? $googleData['picture'] ?? null;

        /** @var User|null $user */
        $user = User::where('email', $email)
            ->when($googleId, fn ($q) => $q->orWhere('google_id', $googleId))
            ->first();

        if ($user) {
            if (!$user->is_active) {
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
            if (empty($user->name) && !empty($name)) {
                $user->name = $name;
            }

            $user->last_active_at = now();
            $user->save();

            // Ensure player profile exists
            if (!$user->playerProfile) {
                $user->playerProfile()->create([
                    'current_level' => 1,
                    'total_xp'      => 0,
                    'streak'        => 0,
                    'avatar'        => $avatar,
                ]);
            } elseif ($avatar && empty($user->playerProfile->avatar)) {
                $user->playerProfile->update(['avatar' => $avatar]);
            }
        } else {
            // Create brand new user via Google
            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'google_id'         => $googleId,
                'email_verified_at' => now(),
                'last_active_at'    => now(),
                'is_active'         => true,
                'role'              => 'user',
            ]);

            $user->playerProfile()->create([
                'current_level' => 1,
                'total_xp'      => 0,
                'streak'        => 0,
                'avatar'        => $avatar,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('playerProfile');

        return [
            'status' => 'success',
            'user'   => $user,
            'token'  => $token,
        ];
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
