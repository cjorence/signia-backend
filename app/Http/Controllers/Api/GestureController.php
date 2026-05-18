<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gesture\StoreGestureRequest;
use App\Http\Resources\GestureLogResource;
use App\Http\Resources\ProgressResource;
use App\Services\GestureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GestureController extends Controller
{
    public function __construct(
        protected GestureService $gestureService
    ) {}

    /**
     * POST /api/user/gestures
     * Submit a gesture attempt from the AI module.
     */
    public function store(StoreGestureRequest $request): JsonResponse
    {
        $result = $this->gestureService->storeGesture(
            Auth::id(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => $result['is_correct']
                ? 'Correct sign! Great job.'
                : 'Keep practicing! Try again.',
            'data'    => [
                'is_correct'  => $result['is_correct'],
                'gesture_log' => new GestureLogResource($result['gesture_log']),
                'progress'    => new ProgressResource($result['progress']),
            ],
        ], 201);
    }

    /**
     * GET /api/user/gestures
     * Get the authenticated user's gesture logs.
     * Optional query params: sign_id, level_id
     */
    public function index(Request $request): JsonResponse
    {
        $logs = $this->gestureService->getUserGestureLogs(
            Auth::id(),
            $request->query('sign_id') ? (int) $request->query('sign_id') : null,
            $request->query('level_id') ? (int) $request->query('level_id') : null,
        );

        return response()->json([
            'success' => true,
            'data'    => GestureLogResource::collection($logs),
        ], 200);
    }

    /**
     * GET /api/user/gestures/accuracy
     * Get user accuracy statistics.
     */
    public function accuracy(): JsonResponse
    {
        $stats = $this->gestureService->getUserAccuracy(Auth::id());

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ], 200);
    }
}