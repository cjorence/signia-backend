<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'purchase_id' => $this->purchase_id,
            'purchase' => $this->whenLoaded('purchase', function () {
                return [
                    'id' => $this->purchase->id,
                    'product_type' => $this->purchase->product_type,
                    'package_key' => $this->purchase->package_key,
                    'story_id' => $this->purchase->story_id,
                    'quantity' => $this->purchase->quantity,
                    'amount' => $this->purchase->amount,
                    'currency' => $this->purchase->currency,
                    'status' => $this->purchase->status,
                    'paid_at' => $this->purchase->paid_at?->toISOString(),
                ];
            }),
            'reason' => $this->reason,
            'explanation' => $this->explanation,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider_status' => $this->provider_status,
            'email_delivery_status' => $this->email_delivery_status,
            'admin_notes' => $this->admin_notes,
            'paymongo_refund_id' => $this->paymongo_refund_id,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'reviewed_by' => $this->reviewed_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
