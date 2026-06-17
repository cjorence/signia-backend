<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\UserAchievementResource;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AchievementController extends Controller
{
    public function __construct(
        protected AchievementService $achievementService
    ) {}

    public function index(): JsonResponse
    {
        $achievements = $this->achievementService->getAllAchievements();

        return response()->json([
            'success' => true,
            'data' => AchievementResource::collection($achievements),
        ], 200);
    }

    public function userAchievements(): JsonResponse
    {
        $achievements = $this->achievementService->getUserAchievements(Auth::id());

        return response()->json([
            'success' => true,
            'data' => UserAchievementResource::collection($achievements),
        ], 200);
    }

    public function check(): JsonResponse
    {
        $unlocked = $this->achievementService->checkAndUnlock(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Achievement check completed.',
            'data' => UserAchievementResource::collection($unlocked),
        ], 200);
    }
}