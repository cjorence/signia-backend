<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    private const HEART_PACKAGES = [
        'small_hearts' => ['quantity' => 5, 'amount' => 49.00, 'currency' => 'PHP'],
        'medium_hearts' => ['quantity' => 15, 'amount' => 129.00, 'currency' => 'PHP'],
        'large_hearts' => ['quantity' => 30, 'amount' => 249.00, 'currency' => 'PHP'],
    ];

    public function createHeartPurchase(User $user, string $packageKey): Purchase
    {
        if (! isset(self::HEART_PACKAGES[$packageKey])) {
            throw ValidationException::withMessages([
                'package_key' => 'Invalid heart package selected.',
            ]);
        }

        $package = self::HEART_PACKAGES[$packageKey];

        return Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'hearts',
            'package_key' => $packageKey,
            'quantity' => $package['quantity'],
            'amount' => $package['amount'],
            'currency' => $package['currency'],
            'status' => 'pending',
        ])->load('user');
    }

    public function getUserPurchases(User $user)
    {
        return Purchase::where('user_id', $user->id)
            ->with('paymentTransactions')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getAllPurchases()
    {
        return Purchase::with(['user', 'paymentTransactions'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function markPaid(Purchase $purchase, string $provider, string $providerReference, HeartService $heartService): Purchase
    {
        if ($purchase->status === 'paid') {
            return $purchase->load(['user', 'paymentTransactions']);
        }

        if ($purchase->status !== 'pending') {
            throw ValidationException::withMessages([
                'purchase' => 'Only pending purchases can be marked as paid.',
            ]);
        }

        if (PaymentTransaction::where('provider_reference', $providerReference)->exists()) {
            throw ValidationException::withMessages([
                'provider_reference' => 'This payment reference has already been processed.',
            ]);
        }

        return DB::transaction(function () use ($purchase, $provider, $providerReference, $heartService) {
            $purchase->update([
                'status' => 'paid',
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'paid_at' => now(),
            ]);

            PaymentTransaction::create([
                'purchase_id' => $purchase->id,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'status' => 'paid',
                'amount' => $purchase->amount,
                'currency' => $purchase->currency,
                'raw_payload' => [
                    'source' => 'manual_admin_mark_paid',
                ],
                'processed_at' => now(),
            ]);

            $heartService->grant(
                $purchase->user,
                $purchase->quantity,
                'purchase',
                'paid_heart_purchase',
                $purchase,
                ['provider_reference' => $providerReference]
            );

            return $purchase->fresh()->load(['user', 'paymentTransactions']);
        });
    }

    public function revenueSummary(): array
    {
        return [
            'total_revenue' => (float) Purchase::where('status', 'paid')->sum('amount'),
            'paid_purchases' => Purchase::where('status', 'paid')->count(),
            'pending_purchases' => Purchase::where('status', 'pending')->count(),
            'failed_purchases' => Purchase::where('status', 'failed')->count(),
            'refunded_purchases' => Purchase::where('status', 'refunded')->count(),
        ];
    }
}