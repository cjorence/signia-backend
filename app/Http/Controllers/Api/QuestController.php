<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quest\StoreQuestRequest;
use App\Http\Requests\Quest\UpdateQuestRequest;
use App\Http\Resources\QuestResource;
use App\Models\Level;
use App\Models\Quest;
use App\Services\QuestService;
use Illuminate\Http\JsonResponse;

class QuestController extends Controller
{
    public function __construct(
        protected QuestService $questService
    ) {}

    public function index(Level $level): JsonResponse
    {
        $quests = $this->questService->getQuestsByLevel($level);

        return response()->json([
            'success' => true,
            'data'    => QuestResource::collection($quests),
        ], 200);
    }

    public function show(Quest $quest): JsonResponse
    {
        $quest = $this->questService->getQuestDetail($quest);

        return response()->json([
            'success' => true,
            'data'    => new QuestResource($quest),
        ], 200);
    }

    public function store(StoreQuestRequest $request): JsonResponse
    {
        $quest = $this->questService->createQuest($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quest created successfully.',
            'data'    => new QuestResource($quest),
        ], 201);
    }

    public function update(UpdateQuestRequest $request, Quest $quest): JsonResponse
    {
        $quest = $this->questService->updateQuest($quest, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Quest updated successfully.',
            'data'    => new QuestResource($quest),
        ], 200);
    }

    public function destroy(Quest $quest): JsonResponse
    {
        $this->questService->deleteQuest($quest);

        return response()->json([
            'success' => true,
            'message' => 'Quest deleted successfully.',
        ], 200);
    }
}