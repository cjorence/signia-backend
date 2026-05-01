<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserQuest\UpdateUserQuestRequest;
use App\Http\Resources\UserQuestResource;
use App\Services\UserQuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserQuestController extends Controller
{
    public function __construct(
        protected UserQuestService $userQuestService
    ) {}

    public function index(): JsonResponse
    {
        $userQuests = $this->userQuestService->getUserQuests(Auth::id());

        return response()->json([
            'success' => true,
            'data'    => UserQuestResource::collection($userQuests),
        ], 200);
    }

    public function updateStatus(UpdateUserQuestRequest $request): JsonResponse
    {
        $result = $this->userQuestService->updateQuestStatus(
            Auth::id(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => new UserQuestResource($result['user_quest']),
        ], 200);
    }
}