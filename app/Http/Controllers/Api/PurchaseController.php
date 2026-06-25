<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\HeartService;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected HeartService $heartService
    ) {}

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchaseService->createHeartPurchase(
            Auth::user(),
            $request->validated('package_key')
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase created successfully. Complete payment to receive hearts.',
            'data' => new PurchaseResource($purchase),
        ], 201);
    }

    public function userPurchases(): JsonResponse
    {
        $purchases = $this->purchaseService->getUserPurchases(Auth::user());

        return response()->json([
            'success' => true,
            'data' => PurchaseResource::collection($purchases),
        ], 200);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        abort_if($purchase->user_id !== Auth::id(), 403);

        return response()->json([
            'success' => true,
            'data' => new PurchaseResource($purchase->load('paymentTransactions')),
        ], 200);
    }

    public function adminPurchases(): JsonResponse
    {
        $purchases = $this->purchaseService->getAllPurchases();

        return response()->json([
            'success' => true,
            'data' => PurchaseResource::collection($purchases),
        ], 200);
    }

    public function revenueSummary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->purchaseService->revenueSummary(),
        ], 200);
    }

    public function markPaid(Request $request, Purchase $purchase): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'provider_reference' => ['required', 'string', 'max:255', 'unique:payment_transactions,provider_reference'],
        ]);

        $purchase = $this->purchaseService->markPaid(
            $purchase,
            $data['provider'],
            $data['provider_reference'],
            $this->heartService
        );

        return response()->json([
            'success' => true,
            'message' => 'Purchase marked as paid and hearts granted.',
            'data' => new PurchaseResource($purchase),
        ], 200);
    }
}