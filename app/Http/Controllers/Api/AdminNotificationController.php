<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(
        protected AdminNotificationService $notificationService
    ) {}

    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->notificationService->getSettings(),
        ], 200);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'newUserAlerts' => 'sometimes|boolean',
            'purchaseAlerts' => 'sometimes|boolean',
            'trainingAlerts' => 'sometimes|boolean',
        ]);

        $settings = $this->notificationService->updateSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Admin notification preferences updated.',
            'data' => $settings,
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        if ($limit < 1 || $limit > 100) {
            $limit = 50;
        }

        $result = $this->notificationService->getNotifications($limit);

        return response()->json([
            'success' => true,
            'data' => $result['notifications'],
            'unread_count' => $result['unread_count'],
        ], 200);
    }

    public function markRead(int $id): JsonResponse
    {
        $updated = $this->notificationService->markAsRead($id);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'unread_count' => $this->notificationService->getNotifications(1)['unread_count'],
        ], 200);
    }

    public function markAllRead(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead();

        return response()->json([
            'success' => true,
            'marked_count' => $count,
            'unread_count' => 0,
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->notificationService->destroy($id);

        return response()->json([
            'success' => $deleted,
            'unread_count' => $this->notificationService->getNotifications(1)['unread_count'],
        ], 200);
    }

    public function clearAll(): JsonResponse
    {
        $this->notificationService->clearAll();

        return response()->json([
            'success' => true,
            'message' => 'All admin notifications cleared.',
            'unread_count' => 0,
        ], 200);
    }

    public function reportTrainingComplete(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string|max:100',
            'accuracy' => 'required|numeric|min:0|max:1',
            'model_path' => 'nullable|string|max:255',
        ]);

        // Older open browser tabs still send this callback. Acknowledge it without
        // creating an alert: the verified Python status response is authoritative.
        return response()->json([
            'success' => true,
            'data' => null,
        ], 200);
    }
}
