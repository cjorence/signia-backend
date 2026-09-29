<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success'    => true,
            'message'    => 'Registration successful.',
            'data'       => [
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result['status'] === 'invalid') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($result['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Login successful.',
            'data'       => [
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    public function me(): JsonResponse
    {
        $user = $this->authService->me(auth()->user());

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => new UserResource($user),
            ],
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ]);

        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided current password does not match.',
                    'errors' => [
                        'current_password' => ['The provided current password does not match.'],
                    ],
                ], 422);
            }

            $user->password = $validated['password'];
        }

        if (isset($validated['name']) && trim($validated['name']) !== '') {
            $user->name = trim($validated['name']);
        }

        $user->save();

        if (array_key_exists('avatar', $validated)) {
            $profile = $user->playerProfile;
            if (!$profile) {
                $user->playerProfile()->create([
                    'avatar' => $validated['avatar'],
                    'current_level' => 1,
                    'total_xp' => 0,
                    'streak' => 0,
                ]);
            } else {
                $profile->avatar = $validated['avatar'];
                $profile->save();
            }
        }

        $user = $this->authService->me($user);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 200);
    }
}