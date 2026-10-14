<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RefundRequestResource;
use App\Models\Purchase;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Submit a refund request for an eligible purchase.
     */
    public function store(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:accidental_duplicate,accidental_purchase,technical_issue,unsatisfied,other'],
            'explanation' => ['nullable', 'string', 'max:1000'],
        ]);

        $refundRequest = $this->refundService->requestRefund(
            Auth::user(),
            $purchase,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund request submitted successfully. You will receive an email confirmation shortly.',
            'data' => new RefundRequestResource($refundRequest),
        ], 201);
    }

    /**
     * Get user's own refund requests history.
     */
    public function userIndex(): JsonResponse
    {
        $requests = RefundRequest::where('user_id', Auth::id())
            ->with('purchase')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => RefundRequestResource::collection($requests),
        ], 200);
    }
}
