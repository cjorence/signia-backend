<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Purchase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function createCheckoutSession(Purchase $purchase): array
    {
        $secretKey = (string) config('services.paymongo.secret_key');

        if ($secretKey === '') {
            throw ValidationException::withMessages([
                'payment' => 'PayMongo is not configured for this environment.',
            ]);
        }

        if ($purchase->status !== 'pending') {
            throw ValidationException::withMessages([
                'purchase' => 'Only pending purchases can start checkout.',
            ]);
        }

        $frontendUrl = rtrim((string) config('services.paymongo.frontend_url'), '/');
        $referenceNumber = 'purchase:'.$purchase->id;
        $amountInCentavos = (int) round(((float) $purchase->amount) * 100);

        if ($purchase->product_type === 'story') {
            $purchase->loadMissing('story');
            $chapterNum = $purchase->story?->chapter_number ?? 1;
            $chapterTitle = $purchase->story?->title ?? 'Story Chapter';
            $lineItemName = 'Signia Quest: Chapter '.$chapterNum.' - '.$chapterTitle;
            $successUrl = $frontendUrl.'/player/story?payment=success&purchase_id='.$purchase->id.'&story_id='.($purchase->story_id ?? '');
            $cancelUrl = $frontendUrl.'/player/story?payment=cancelled&story_id='.($purchase->story_id ?? '');
        } else {
            $lineItemName = $purchase->quantity.' Signia Heart Credits';
            $successUrl = $frontendUrl.'/player/store?payment=success&purchase_id='.$purchase->id;
            $cancelUrl = $frontendUrl.'/player/store?payment=cancelled&purchase_id='.$purchase->id;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withBasicAuth($secretKey, '')
                ->post(rtrim((string) config('services.paymongo.api_url'), '/').'/v2/checkout_sessions', [
                    'data' => [
                        'attributes' => [
                            'line_items' => [[
                                'name' => $lineItemName,
                                'amount' => $amountInCentavos,
                                'currency' => $purchase->currency,
                                'quantity' => 1,
                            ]],
                            'payment_method_types' => ['card'],
                            'success_url' => $successUrl,
                            'cancel_url' => $cancelUrl,
                            'reference_number' => $referenceNumber,
                            'metadata' => [
                                'purchase_id' => (string) $purchase->id,
                                'user_id' => (string) $purchase->user_id,
                                'product_type' => (string) $purchase->product_type,
                                'story_id' => (string) ($purchase->story_id ?? ''),
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages([
                'payment' => 'Unable to reach PayMongo. Please try again shortly.',
            ]);
        }

        try {
            $response->throw();
        } catch (RequestException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'payment' => 'PayMongo could not create a checkout session. Please try again.',
            ]);
        }

        $checkoutSessionId = data_get($response->json(), 'data.id');
        $checkoutUrl = data_get($response->json(), 'data.attributes.checkout_url');

        if (! is_string($checkoutSessionId) || ! is_string($checkoutUrl)) {
            throw ValidationException::withMessages([
                'payment' => 'PayMongo returned an invalid checkout response.',
            ]);
        }

        $purchase->update([
            'provider' => 'paymongo',
            'checkout_session_id' => $checkoutSessionId,
        ]);

        return [
            'purchase' => $purchase->fresh(),
            'checkout_url' => $checkoutUrl,
        ];
    }

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

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        $webhookSecret = (string) config('services.paymongo.webhook_secret');

        if ($webhookSecret === '' || ! $signatureHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value !== null) {
                $parts[$key] = $value;
            }
        }

        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        $signatureKey = config('services.paymongo.mode') === 'live' ? 'li' : 'te';
        $receivedSignature = $parts[$signatureKey] ?? null;

        if (! $timestamp || ! is_string($receivedSignature)) {
            return false;
        }

        if (abs(now()->timestamp - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $webhookSecret);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    public function fulfillCheckoutPayment(array $event): ?Purchase
    {
        $eventType = data_get($event, 'data.attributes.type');

        if ($eventType !== 'checkout_session.payment.paid') {
            return null;
        }

        $checkoutSession = data_get($event, 'data.attributes.data');
        $checkoutSessionId = data_get($checkoutSession, 'id');
        $referenceNumber = data_get($checkoutSession, 'attributes.reference_number');
        $isLiveMode = (bool) data_get($event, 'data.attributes.livemode', false);
        $expectedLiveMode = config('services.paymongo.mode') === 'live';

        if (! is_array($checkoutSession) || ! is_string($checkoutSessionId) || ! is_string($referenceNumber)) {
            throw ValidationException::withMessages([
                'payment' => 'PayMongo webhook payload is missing checkout details.',
            ]);
        }

        if ($isLiveMode !== $expectedLiveMode) {
            throw ValidationException::withMessages([
                'payment' => 'PayMongo webhook mode does not match this environment.',
            ]);
        }

        if (! preg_match('/^purchase:(\d+)$/', $referenceNumber, $matches)) {
            throw ValidationException::withMessages([
                'payment' => 'PayMongo webhook reference is invalid.',
            ]);
        }

        return DB::transaction(function () use ($checkoutSessionId, $matches, $event) {
            $purchase = Purchase::with('user')
                ->whereKey((int) $matches[1])
                ->lockForUpdate()
                ->first();

            if (! $purchase || $purchase->provider !== 'paymongo' || $purchase->checkout_session_id !== $checkoutSessionId) {
                throw ValidationException::withMessages([
                    'payment' => 'PayMongo checkout session does not match a pending purchase.',
                ]);
            }

            if ($purchase->status === 'paid') {
                return $purchase;
            }

            if ($purchase->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => 'This purchase can no longer be paid.',
                ]);
            }

            PaymentTransaction::firstOrCreate(
                ['provider_reference' => $checkoutSessionId],
                [
                    'purchase_id' => $purchase->id,
                    'provider' => 'paymongo',
                    'status' => 'paid',
                    'amount' => $purchase->amount,
                    'currency' => $purchase->currency,
                    'raw_payload' => $event,
                    'processed_at' => now(),
                ]
            );

            $purchase->update([
                'status' => 'paid',
                'provider_reference' => $checkoutSessionId,
                'paid_at' => now(),
            ]);

            if ($purchase->product_type === 'story') {
                $purchase->loadMissing('story');
                if ($purchase->story) {
                    app(StoryService::class)->unlockForUser(
                        $purchase->user,
                        $purchase->story,
                        $purchase
                    );
                }
            } else {
                app(HeartService::class)->creditInventory(
                    $purchase->user,
                    $purchase->quantity,
                    'paymongo_checkout_paid',
                    $purchase,
                    ['checkout_session_id' => $checkoutSessionId]
                );
            }

            return $purchase->fresh()->load(['user', 'paymentTransactions', 'story']);
        });
    }

    public function verifyCheckoutSession(Purchase $purchase): Purchase
    {
        if ($purchase->status === 'paid') {
            return $purchase->load(['user', 'paymentTransactions', 'story']);
        }

        if ($purchase->status !== 'pending' || ! $purchase->checkout_session_id) {
            return $purchase;
        }

        $secretKey = (string) config('services.paymongo.secret_key');
        if ($secretKey === '') {
            return $purchase;
        }

        try {
            $response = Http::acceptJson()
                ->withBasicAuth($secretKey, '')
                ->get(rtrim((string) config('services.paymongo.api_url'), '/').'/v2/checkout_sessions/'.$purchase->checkout_session_id);

            if ($response->successful()) {
                $sessionData = $response->json('data.attributes');
                $payments = $sessionData['payments'] ?? [];
                $isPaid = false;

                foreach ($payments as $payment) {
                    if (data_get($payment, 'attributes.status') === 'paid') {
                        $isPaid = true;
                        break;
                    }
                }

                if ($isPaid) {
                    $fakeEvent = [
                        'data' => [
                            'attributes' => [
                                'type' => 'checkout_session.payment.paid',
                                'livemode' => config('services.paymongo.mode') === 'live',
                                'data' => [
                                    'id' => $purchase->checkout_session_id,
                                    'attributes' => [
                                        'reference_number' => 'purchase:'.$purchase->id,
                                    ],
                                ],
                            ],
                        ],
                    ];

                    return $this->fulfillCheckoutPayment($fakeEvent) ?? $purchase->fresh();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $purchase->fresh()->load(['user', 'paymentTransactions', 'story']);
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
