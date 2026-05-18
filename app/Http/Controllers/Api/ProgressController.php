<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Progress\UpdateProgressRequest;
use App\Http\Resources\ProgressResource;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProgressController extends Controller
{
    public function __construct(
        protected ProgressService $progressService
    ) {}

    /**
     * GET /api/user/progress
     * Get all progress for authenticated user.
     */
    public function index(): JsonResponse
    {
        $progress = $this->progressService->getUserProgress(Auth::id());

        return response()->json([
            'success' => true,
            'data'    => ProgressResource::collection($progress),
        ], 200);
    }

    /**
     * GET /api/user/progress/level/{levelId}
     * Get user's progress for a specific level.
     */
    public function byLevel(int $levelId): JsonResponse
    {
        $progress = $this->progressService->getUserProgressByLevel(Auth::id(), $levelId);

        return response()->json([
            'success' => true,
            'data'    => ProgressResource::collection($progress),
        ], 200);
    }

    /**
     * GET /api/user/progress/sign/{signId}
     * Get user's progress for a specific sign.
     */
    public function bySign(int $signId): JsonResponse
    {
        $progress = $this->progressService->getUserProgressBySign(Auth::id(), $signId);

        if (!$progress) {
            return response()->json([
                'success' => true,
                'message' => 'No progress recorded for this sign yet.',
                'data'    => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data'    => new ProgressResource($progress),
        ], 200);
    }

    /**
     * POST /api/user/progress
     * Manually update progress.
     */
    public function update(UpdateProgressRequest $request): JsonResponse
    {
        $progress = $this->progressService->updateProgress(
            Auth::id(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully.',
            'data'    => new ProgressResource($progress),
        ], 200);
    }
}