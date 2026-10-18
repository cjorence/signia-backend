<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Heart\AdminGrantHeartsBulkRequest;
use App\Http\Requests\Heart\AdminGrantHeartsRequest;
use App\Http\Requests\Heart\RefillHeartInventoryRequest;
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

    public function refill(RefillHeartInventoryRequest $request): JsonResponse
    {
        $profile = $this->heartService->refillFromInventory(
            Auth::user(),
            $request->validated('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Usable hearts refilled from inventory.',
            'data' => new PlayerProfileResource($profile),
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

    public function grantBulk(AdminGrantHeartsBulkRequest $request): JsonResponse
    {
        $amount = (int) $request->validated('amount');
        $reason = $request->validated('reason') ?? 'admin_manual_grant';
        $grantAll = filter_var($request->input('grant_all'), FILTER_VALIDATE_BOOLEAN);

        if ($grantAll) {
            $users = User::where('role', '!=', 'admin')->get();
            if ($users->isEmpty()) {
                $users = User::all();
            }
        } else {
            $userIds = $request->validated('user_ids', []);
            $users = User::whereIn('id', $userIds)->get();
        }

        $count = 0;
        foreach ($users as $user) {
            $this->heartService->grant(
                $user,
                $amount,
                'admin_grant',
                $reason,
                null,
                ['admin_id' => Auth::id(), 'bulk' => true]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully granted {$amount} hearts to {$count} player" . ($count === 1 ? '' : 's') . '.',
            'data' => [
                'count' => $count,
                'amount' => $amount,
            ],
        ], 200);
    }
}
