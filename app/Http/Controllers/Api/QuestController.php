<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quest\StoreQuestRequest;
use App\Http\Requests\Quest\UpdateQuestRequest;
use App\Http\Resources\QuestResource;
use App\Models\Level;
use App\Models\Quest;
use Illuminate\Http\JsonResponse;

class QuestController extends Controller
{
    /**
     * GET /api/levels/{level}/quests
     * Public: Get all quests for a specific level.
     */
    public function index(Level $level): JsonResponse
    {
        $quests = $level->quests()->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => QuestResource::collection($quests),
        ], 200);
    }

    /**
     * GET /api/quests/{quest}
     * Public: Show a single quest.
     */
    public function show(Quest $quest): JsonResponse
    {
        $quest->load('level');

        return response()->json([
            'success' => true,
            'data'    => new QuestResource($quest),
        ], 200);
    }

    /**
     * POST /api/admin/quests
     * Admin only: Create a new quest.
     */
    public function store(StoreQuestRequest $request): JsonResponse
    {
        $quest = Quest::create($request->validated());
        $quest->load('level');

        return response()->json([
            'success' => true,
            'message' => 'Quest created successfully.',
            'data'    => new QuestResource($quest),
        ], 201);
    }

    /**
     * PUT /api/admin/quests/{quest}
     * Admin only: Update a quest.
     */
    public function update(UpdateQuestRequest $request, Quest $quest): JsonResponse
    {
        $quest->update($request->validated());
        $quest->load('level');

        return response()->json([
            'success' => true,
            'message' => 'Quest updated successfully.',
            'data'    => new QuestResource($quest),
        ], 200);
    }

    /**
     * DELETE /api/admin/quests/{quest}
     * Admin only: Delete a quest.
     */
    public function destroy(Quest $quest): JsonResponse
    {
        $quest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quest deleted successfully.',
        ], 200);
    }
}