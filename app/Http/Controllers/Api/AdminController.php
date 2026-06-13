<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLogResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    public function users(): JsonResponse
    {
        $users = $this->adminService->getUsers();

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ], 200);
    }

    public function showUser(User $user): JsonResponse
    {
        $user = $this->adminService->getUserDetail($user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ], 200);
    }

    public function activateUser(User $user): JsonResponse
    {
        $user = $this->adminService->activateUser(Auth::user(), $user);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully.',
            'data' => new UserResource($user),
        ], 200);
    }

    public function deactivateUser(User $user): JsonResponse
    {
        $user = $this->adminService->deactivateUser(Auth::user(), $user);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully.',
            'data' => new UserResource($user),
        ], 200);
    }

    public function analytics(): JsonResponse
    {
        $analytics = $this->adminService->getAnalytics();

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ], 200);
    }

    public function logs(): JsonResponse
    {
        $logs = $this->adminService->getAdminLogs();

        return response()->json([
            'success' => true,
            'data' => AdminLogResource::collection($logs),
        ], 200);
    }
}