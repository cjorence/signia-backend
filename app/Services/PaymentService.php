<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Purchase;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function recordTransaction(Purchase $purchase, array $data): PaymentTransaction
    {
        $existing = PaymentTransaction::where('provider_reference', $data['provider_reference'])->first();

        if ($existing) {
            return $existing;
        }

        return PaymentTransaction::create([
            'purchase_id' => $purchase->id,
            'provider' => $data['provider'],
            'provider_reference' => $data['provider_reference'],
            'status' => $data['status'],
            'amount' => $purchase->amount,
            'currency' => $purchase->currency,
            'raw_payload' => $data['raw_payload'] ?? null,
            'processed_at' => now(),
        ]);
    }

    public function webhookPlaceholder(): array
    {
        return [
            'message' => 'Payment webhook endpoint ready. Gateway verification not configured yet.',
        ];
    }

    public function ensureProviderReferenceIsUnused(string $providerReference): void
    {
        if (PaymentTransaction::where('provider_reference', $providerReference)->exists()) {
            throw ValidationException::withMessages([
                'provider_reference' => 'This payment reference has already been processed.',
            ]);
        }
    }
}