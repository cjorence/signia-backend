<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserQuest\UpdateUserQuestRequest;
use App\Http\Resources\UserQuestResource;
use App\Models\UserQuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserQuestController extends Controller
{
    /**
     * GET /api/user/quests
     * User: Get all of the authenticated user's quests.
     */
    public function index(): JsonResponse
    {
        $userQuests = UserQuest::where('user_id', Auth::id())
                               ->with('quest.level')
                               ->orderBy('created_at', 'desc')
                               ->get();

        return response()->json([
            'success' => true,
            'data'    => UserQuestResource::collection($userQuests),
        ], 200);
    }

    /**
     * POST /api/user/quests
     * User: Start or update a quest.
     */
    public function updateStatus(UpdateUserQuestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $userQuest = UserQuest::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'quest_id' => $validated['quest_id'],
            ],
            [
                'status' => $validated['status'],
            ]
        );

        $userQuest->load('quest.level');

        $message = $validated['status'] === 'completed'
            ? 'Quest completed!'
            : 'Quest status updated.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => new UserQuestResource($userQuest),
        ], 200);
    }
}