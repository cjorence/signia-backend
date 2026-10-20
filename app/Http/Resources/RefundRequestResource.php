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
            'diagnostics' => $this->when(
                $request->user()?->role === 'admin' || str_contains($request->path(), 'admin') || str_contains($request->path(), 'refund-requests'),
                function () {
                    $purchase = $this->relationLoaded('purchase') ? $this->purchase : $this->purchase()->first();
                    if (! $purchase) {
                        return null;
                    }

                    // Check for duplicate orders within 24 hours
                    $duplicateOrder = \App\Models\Purchase::where('user_id', $this->user_id)
                        ->where('id', '!=', $purchase->id)
                        ->where('product_type', $purchase->product_type)
                        ->where('package_key', $purchase->package_key)
                        ->where('status', 'paid')
                        ->whereBetween('created_at', [
                            $purchase->created_at->subHours(24),
                            $purchase->created_at->addHours(24),
                        ])
                        ->latest()
                        ->first(['id', 'amount', 'created_at']);

                    $isHeartPurchase = in_array($purchase->product_type, ['heart', 'hearts'], true);
                    $heartUsage = $isHeartPurchase
                        ? app(\App\Services\HeartService::class)->getPurchaseConsumableUsage($purchase)
                        : ['hearts_purchased' => 0, 'hearts_remaining' => 0, 'hearts_used' => 0, 'hearts_intact' => true];

                    $isStoryPurchase = $purchase->product_type === 'story';
                    $storyUnlocked = $isStoryPurchase && $purchase->story_id
                        ? \App\Models\UserStoryUnlock::where('user_id', $this->user_id)->where('story_id', $purchase->story_id)->exists()
                        : false;

                    return [
                        'is_heart_purchase' => $isHeartPurchase,
                        'hearts_purchased' => $heartUsage['hearts_purchased'],
                        'current_hearts' => $heartUsage['hearts_remaining'],
                        'hearts_used' => $heartUsage['hearts_used'],
                        'hearts_intact' => $heartUsage['hearts_intact'],
                        'is_story_purchase' => $isStoryPurchase,
                        'story_id' => $purchase->story_id,
                        'story_unlocked' => $storyUnlocked,
                        'duplicate_order' => $duplicateOrder ? [
                            'id' => $duplicateOrder->id,
                            'amount' => (float) $duplicateOrder->amount,
                            'created_at' => $duplicateOrder->created_at->toISOString(),
                        ] : null,
                        'recommended_cut_amount' => round(((float) $this->amount) * 0.80, 2),
                        'full_amount' => (float) $this->amount,
                    ];
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
