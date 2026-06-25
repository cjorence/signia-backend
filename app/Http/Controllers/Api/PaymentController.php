<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function webhook(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->paymentService->webhookPlaceholder(),
        ], 200);
    }
}