<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLogResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use App\Services\CurriculumEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

    public function getMaintenance(): JsonResponse
    {
        $state = Cache::get('platform_maintenance_state', [
            'enabled'    => false,
            'message'    => 'Signia is currently undergoing routine curriculum updates. Practice will resume shortly.',
            'updated_at' => (int) round(microtime(true) * 1000),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $state,
        ], 200);
    }

    public function updateMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        $enabled = (bool) $validated['enabled'];
        $message = trim($validated['message'] ?? '') ?: 'Signia is currently undergoing routine curriculum updates. Practice will resume shortly.';
        $now = (int) round(microtime(true) * 1000);

        // Fetch previous state to detect real state transitions
        $previousState = Cache::get('platform_maintenance_state', [
            'enabled'    => false,
            'message'    => '',
            'updated_at' => 0,
        ]);
        $wasEnabled = (bool) ($previousState['enabled'] ?? false);
        $previousMessage = $previousState['message'] ?? '';

        $state = [
            'enabled'    => $enabled,
            'message'    => $message,
            'updated_at' => $now,
        ];

        Cache::forever('platform_maintenance_state', $state);

        $admin = Auth::user();

        // ONLY record a broadcast event on genuine state transitions
        if (!$wasEnabled && $enabled) {
            // State: OFF -> ON (Maintenance activated)
            CurriculumEventService::record('platform-maintenance', 1, $message, 'active');
            if ($admin) {
                $this->adminService->logAction($admin, 'Enabled platform maintenance mode');
            }
        } elseif ($wasEnabled && !$enabled) {
            // State: ON -> OFF (Maintenance deactivated)
            CurriculumEventService::record('platform-maintenance', 0, 'Platform maintenance has concluded. All learning systems are fully operational!', 'resolved');
            if ($admin) {
                $this->adminService->logAction($admin, 'Disabled platform maintenance mode');
            }
        } elseif ($wasEnabled && $enabled && $message !== $previousMessage) {
            // State: ON -> ON with new message
            CurriculumEventService::record('platform-maintenance', 1, $message, 'active');
            if ($admin) {
                $this->adminService->logAction($admin, 'Updated active maintenance message');
            }
        }
        // If !wasEnabled && !enabled: NOTHING is recorded. Zero spam to learners!

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Maintenance mode enabled and broadcasted.' : 'Maintenance settings saved.',
            'data'    => $state,
        ], 200);
    }
}