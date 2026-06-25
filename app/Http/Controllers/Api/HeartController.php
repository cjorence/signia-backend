<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Heart\AdminGrantHeartsRequest;
use App\Http\Resources\HeartTransactionResource;
use App\Http\Resources\PlayerProfileResource;
use App\Models\User;
use App\Services\HeartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HeartController extends Controller
{
    public function __construct(
        protected HeartService $heartService
    ) {}

    public function status(): JsonResponse
    {
        $profile = $this->heartService->status(Auth::user());

        return response()->json([
            'success' => true,
            'data' => new PlayerProfileResource($profile),
        ], 200);
    }

    public function transactions(): JsonResponse
    {
        $transactions = $this->heartService->transactions(Auth::user());

        return response()->json([
            'success' => true,
            'data' => HeartTransactionResource::collection($transactions),
        ], 200);
    }

    public function adminTransactions(): JsonResponse
    {
        $transactions = $this->heartService->allTransactions();

        return response()->json([
            'success' => true,
            'data' => HeartTransactionResource::collection($transactions),
        ], 200);
    }

    public function grant(AdminGrantHeartsRequest $request, User $user): JsonResponse
    {
        $profile = $this->heartService->grant(
            $user,
            $request->validated('amount'),
            'admin_grant',
            $request->validated('reason') ?? 'admin_manual_grant',
            null,
            ['admin_id' => Auth::id()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Hearts granted successfully.',
            'data' => new PlayerProfileResource($profile),
        ], 200);
    }
}