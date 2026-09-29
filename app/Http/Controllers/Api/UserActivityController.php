<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserActivityController extends Controller
{
    public function __construct(
        protected UserActivityService $userActivityService
    ) {}

    /**
     * GET /api/user/activities
     * Return unified recent activity for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 10)));
        $activities = $this->userActivityService->getUserActivities((int) Auth::id(), $limit);

        return response()->json([
            'status' => 'success',
            'data'   => $activities,
        ], 200);
    }
}
