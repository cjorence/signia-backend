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
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
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

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
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
        return $user->load('playerProfile');
    }
}
