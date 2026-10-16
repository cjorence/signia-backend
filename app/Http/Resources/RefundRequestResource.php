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

                    $profile = \App\Models\PlayerProfile::where('user_id', $this->user_id)->first();
                    $totalHearts = ($profile?->hearts ?? 0) + ($profile?->heart_inventory ?? 0);
                    $heartsPurchased = (int) ($purchase->quantity ?? 0);

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
                    $heartsIntact = $isHeartPurchase ? ($totalHearts >= $heartsPurchased) : true;
                    $heartsUsed = $isHeartPurchase ? max(0, $heartsPurchased - $totalHearts) : 0;

                    $isStoryPurchase = $purchase->product_type === 'story';
                    $storyUnlocked = $isStoryPurchase && $purchase->story_id
                        ? \App\Models\UserStoryUnlock::where('user_id', $this->user_id)->where('story_id', $purchase->story_id)->exists()
                        : false;

                    return [
                        'is_heart_purchase' => $isHeartPurchase,
                        'hearts_purchased' => $heartsPurchased,
                        'current_hearts' => $totalHearts,
                        'hearts_used' => $heartsUsed,
                        'hearts_intact' => $heartsIntact,
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
