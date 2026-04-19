<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sign\StoreSignRequest;
use App\Http\Requests\Sign\UpdateSignRequest;
use App\Http\Resources\SignResource;
use App\Models\Level;
use App\Models\Sign;
use Illuminate\Http\JsonResponse;

class SignController extends Controller
{
    /**
     * GET /api/levels/{level}/signs
     * Public: Get all signs for a specific level.
     */
    public function index(Level $level): JsonResponse
    {
        $signs = $level->signs()->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => SignResource::collection($signs),
        ], 200);
    }

    /**
     * GET /api/signs/{sign}
     * Public: Show a single sign.
     */
    public function show(Sign $sign): JsonResponse
    {
        $sign->load('level');

        return response()->json([
            'success' => true,
            'data'    => new SignResource($sign),
        ], 200);
    }

    /**
     * POST /api/admin/signs
     * Admin only: Create a new sign.
     */
    public function store(StoreSignRequest $request): JsonResponse
    {
        $sign = Sign::create($request->validated());
        $sign->load('level');

        return response()->json([
            'success' => true,
            'message' => 'Sign created successfully.',
            'data'    => new SignResource($sign),
        ], 201);
    }

    /**
     * PUT /api/admin/signs/{sign}
     * Admin only: Update a sign.
     */
    public function update(UpdateSignRequest $request, Sign $sign): JsonResponse
    {
        $sign->update($request->validated());
        $sign->load('level');

        return response()->json([
            'success' => true,
            'message' => 'Sign updated successfully.',
            'data'    => new SignResource($sign),
        ], 200);
    }

    /**
     * DELETE /api/admin/signs/{sign}
     * Admin only: Delete a sign.
     */
    public function destroy(Sign $sign): JsonResponse
    {
        $sign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sign deleted successfully.',
        ], 200);
    }
}