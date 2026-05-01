<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sign\StoreSignRequest;
use App\Http\Requests\Sign\UpdateSignRequest;
use App\Http\Resources\SignResource;
use App\Models\Level;
use App\Models\Sign;
use App\Services\SignService;
use Illuminate\Http\JsonResponse;

class SignController extends Controller
{
    public function __construct(
        protected SignService $signService
    ) {}

    public function index(Level $level): JsonResponse
    {
        $signs = $this->signService->getSignsByLevel($level);

        return response()->json([
            'success' => true,
            'data'    => SignResource::collection($signs),
        ], 200);
    }

    public function show(Sign $sign): JsonResponse
    {
        $sign = $this->signService->getSignDetail($sign);

        return response()->json([
            'success' => true,
            'data'    => new SignResource($sign),
        ], 200);
    }

    public function store(StoreSignRequest $request): JsonResponse
    {
        $sign = $this->signService->createSign($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sign created successfully.',
            'data'    => new SignResource($sign),
        ], 201);
    }

    public function update(UpdateSignRequest $request, Sign $sign): JsonResponse
    {
        $sign = $this->signService->updateSign($sign, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sign updated successfully.',
            'data'    => new SignResource($sign),
        ], 200);
    }

    public function destroy(Sign $sign): JsonResponse
    {
        $this->signService->deleteSign($sign);

        return response()->json([
            'success' => true,
            'message' => 'Sign deleted successfully.',
        ], 200);
    }
}