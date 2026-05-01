<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

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
}