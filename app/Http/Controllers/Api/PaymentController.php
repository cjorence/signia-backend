<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();

        if (! $this->paymentService->verifyWebhookSignature(
            $rawPayload,
            $request->header('Paymongo-Signature')
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment webhook signature.',
            ], 401);
        }

        try {
            $event = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
            $purchase = $this->paymentService->fulfillCheckoutPayment($event);
        } catch (\JsonException|ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Payment webhook could not be processed.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $purchase ? new PurchaseResource($purchase) : null,
        ], 200);
    }
}
