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
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_transactions' => PaymentTransactionResource::collection($this->whenLoaded('paymentTransactions')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
