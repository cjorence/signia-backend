<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create user — password auto-hashed via 'hashed' cast
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Auto-create player profile
        $user->playerProfile()->create([
            'current_level' => 1,
            'total_xp'      => 0,
            'streak'        => 0,
        ]);

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relationship for response
        $user->load('playerProfile');

        return response()->json([
            'success'    => true,
            'message'    => 'Registration successful.',
            'data'       => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Login an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Attempt authentication
        if (!Auth::attempt([
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        // Check if account is active
        if (!$user->is_active) {
            Auth::guard('web')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        // Update last active
        $user->update(['last_active_at' => now()]);

        // Generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relationship
        $user->load('playerProfile');

        return response()->json([
            'success'    => true,
            'message'    => 'Login successful.',
            'data'       => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('playerProfile');

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => new UserResource($user),
            ],
        ], 200);
    }
}