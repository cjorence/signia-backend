<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Level\StoreLevelRequest;
use App\Http\Requests\Level\UpdateLevelRequest;
use App\Http\Resources\LevelResource;
use App\Models\Level;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    public function __construct(
        protected LevelService $levelService
    ) {}

    public function index(): JsonResponse
    {
        $levels = $this->levelService->getAllLevels();

        return response()->json([
            'success' => true,
            'data'    => LevelResource::collection($levels),
        ], 200);
    }

    public function show(Level $level): JsonResponse
    {
        $level = $this->levelService->getLevelDetail($level);

        return response()->json([
            'success' => true,
            'data'    => new LevelResource($level),
        ], 200);
    }

    public function store(StoreLevelRequest $request): JsonResponse
    {
        $level = $this->levelService->createLevel($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Level created successfully.',
            'data'    => new LevelResource($level),
        ], 201);
    }

    public function update(UpdateLevelRequest $request, Level $level): JsonResponse
    {
        $level = $this->levelService->updateLevel($level, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Level updated successfully.',
            'data'    => new LevelResource($level),
        ], 200);
    }

    public function destroy(Level $level): JsonResponse
    {
        $this->levelService->deleteLevel($level);

        return response()->json([
            'success' => true,
            'message' => 'Level deleted successfully.',
        ], 200);
    }
}