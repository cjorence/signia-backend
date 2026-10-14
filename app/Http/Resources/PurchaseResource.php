<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'product_type' => $this->product_type,
            'story_id' => $this->story_id,
            'package_key' => $this->package_key,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'checkout_session_id' => $this->checkout_session_id,
            'paymongo_payment_id' => $this->paymongo_payment_id,
            'paymongo_refund_id' => $this->paymongo_refund_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_transactions' => PaymentTransactionResource::collection($this->whenLoaded('paymentTransactions')),
            'refund_request' => new RefundRequestResource($this->whenLoaded('latestRefundRequest')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
