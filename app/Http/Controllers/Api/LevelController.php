<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Level\StoreLevelRequest;
use App\Http\Requests\Level\UpdateLevelRequest;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    /**
     * GET /api/levels
     * Public: List all levels ordered by 'order' field.
     */
    public function index(): JsonResponse
    {
        $levels = Level::withCount(['signs', 'quests'])
                       ->orderBy('order')
                       ->get();

        return response()->json([
            'success' => true,
            'data'    => LevelResource::collection($levels),
        ], 200);
    }

    /**
     * GET /api/levels/{level}
     * Public: Show a single level with its signs and quests.
     */
    public function show(Level $level): JsonResponse
    {
        $level->load(['signs', 'quests']);
        $level->loadCount(['signs', 'quests']);

        return response()->json([
            'success' => true,
            'data'    => new LevelResource($level),
        ], 200);
    }

    /**
     * POST /api/admin/levels
     * Admin only: Create a new level.
     */
    public function store(StoreLevelRequest $request): JsonResponse
    {
        $level = Level::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Level created successfully.',
            'data'    => new LevelResource($level),
        ], 201);
    }

    /**
     * PUT /api/admin/levels/{level}
     * Admin only: Update a level.
     */
    public function update(UpdateLevelRequest $request, Level $level): JsonResponse
    {
        $level->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Level updated successfully.',
            'data'    => new LevelResource($level),
        ], 200);
    }

    /**
     * DELETE /api/admin/levels/{level}
     * Admin only: Delete a level.
     */
    public function destroy(Level $level): JsonResponse
    {
        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Level deleted successfully.',
        ], 200);
    }
}