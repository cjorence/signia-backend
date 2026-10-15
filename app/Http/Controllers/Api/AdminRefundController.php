<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RefundRequestResource;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminRefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Get all refund requests with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $this->refundService->syncProcessingRefunds(null, 1, 2);
        $query = RefundRequest::with(['user', 'purchase.paymentTransactions', 'reviewer'])
            ->latest();

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $requests = $query->get();

        return response()->json([
            'success' => true,
            'data' => RefundRequestResource::collection($requests),
            'summary' => [
                'pending_count' => RefundRequest::where('status', 'pending')->count(),
                'approved_count' => RefundRequest::whereIn('status', ['approved', 'refunded'])->count(),
                'rejected_count' => RefundRequest::where('status', 'rejected')->count(),
            ],
        ], 200);
    }

    /**
     * Approve and execute refund via PayMongo.
     */
    public function approve(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->refundService->approve(
            $refundRequest,
            Auth::user(),
            $validated['admin_notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund decision saved. Check the processing and email status.',
            'data' => new RefundRequestResource($updated),
        ], 200);
    }

    /**
     * Reject refund request with explanation.
     */
    public function reject(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updated = $this->refundService->reject(
            $refundRequest,
            Auth::user(),
            $validated['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund request declined.',
            'data' => new RefundRequestResource($updated),
        ], 200);
    }
}
