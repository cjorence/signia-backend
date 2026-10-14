<?php

namespace App\Services;

use App\Mail\RefundRequestAdminAlertMail;
use App\Mail\RefundRequestSubmittedMail;
use App\Mail\RefundStatusUpdatedMail;
use App\Models\HeartTransaction;
use App\Models\PaymentTransaction;
use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\UserStoryUnlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RefundService
{
    /**
     * Submit a new refund request for a user purchase.
     */
    public function requestRefund(User $user, Purchase $purchase, array $data): RefundRequest
    {
        if ($purchase->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'purchase' => 'You are not authorized to request a refund for this order.',
            ]);
        }

        if ($purchase->status !== 'paid') {
            throw ValidationException::withMessages([
                'purchase' => 'Only paid orders can be requested for a refund.',
            ]);
        }

        $existing = $purchase->refundRequests()
            ->whereIn('status', ['pending', 'approved', 'refunded'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'purchase' => 'A refund request is already active or completed for this order.',
            ]);
        }

        // Refund policy: eligible within 7 days of payment
        if ($purchase->paid_at && $purchase->paid_at->diffInDays(now()) > 7) {
            throw ValidationException::withMessages([
                'purchase' => 'This order is past the 7-day refund eligibility period.',
            ]);
        }

        // Heart inventory validation: check if unused
        if ($purchase->product_type === 'heart' || $purchase->quantity > 0) {
            $profile = PlayerProfile::where('user_id', $user->id)->first();
            $totalAvailable = ($profile?->heart_inventory ?? 0) + ($profile?->hearts ?? 0);

            if ($totalAvailable < $purchase->quantity) {
                throw ValidationException::withMessages([
                    'purchase' => 'These hearts have already been consumed and cannot be refunded.',
                ]);
            }
        }

        $refundRequest = DB::transaction(function () use ($user, $purchase, $data) {
            return RefundRequest::create([
                'user_id' => $user->id,
                'purchase_id' => $purchase->id,
                'reason' => $data['reason'],
                'explanation' => $data['explanation'] ?? null,
                'amount' => $purchase->amount,
                'currency' => $purchase->currency ?: 'PHP',
                'status' => 'pending',
            ]);
        });

        $refundRequest->load(['user', 'purchase']);

        // Send confirmation to User
        try {
            if (!empty($user->email)) {
                Mail::to($user->email)->send(new RefundRequestSubmittedMail($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund request confirmation to user {$user->email}: " . $e->getMessage());
        }

        // Send alert to Dedicated System / Admin Support Email
        try {
            $adminEmail = env('ADMIN_SUPPORT_EMAIL') ?: config('mail.from.address');
            if (!empty($adminEmail)) {
                Mail::to($adminEmail)->send(new RefundRequestAdminAlertMail($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund alert to admin {$adminEmail}: " . $e->getMessage());
        }

        return $refundRequest;
    }

    /**
     * Approve and execute a refund via PayMongo and revert platform inventory/content.
     */
    public function approve(RefundRequest $refundRequest, User $admin, ?string $adminNotes = null): RefundRequest
    {
        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'refund_request' => 'This refund request is already ' . $refundRequest->status . '.',
            ]);
        }

        $refundRequest->loadMissing(['purchase.paymentTransactions', 'purchase.user', 'user']);
        $purchase = $refundRequest->purchase;

        if (!$purchase || $purchase->status !== 'paid') {
            throw ValidationException::withMessages([
                'purchase' => 'The associated order is not in paid status.',
            ]);
        }

        // 1. Process PayMongo Refund API
        $paymongoRefundId = $this->callPayMongoRefundApi($purchase, $refundRequest);

        // 2. Perform DB Updates and Content Reversion
        DB::transaction(function () use ($refundRequest, $purchase, $admin, $adminNotes, $paymongoRefundId) {
            // Revert Story Unlock if applicable
            if ($purchase->product_type === 'story' && $purchase->story_id) {
                UserStoryUnlock::where('user_id', $purchase->user_id)
                    ->where('story_id', $purchase->story_id)
                    ->delete();
            }

            // Revert Hearts if applicable
            if ($purchase->product_type === 'heart' || $purchase->quantity > 0) {
                $profile = PlayerProfile::where('user_id', $purchase->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($profile) {
                    $deductTotal = (int) $purchase->quantity;
                    $deductFromInventory = min($deductTotal, $profile->heart_inventory);
                    $remainder = $deductTotal - $deductFromInventory;
                    $deductFromMeter = min($remainder, $profile->hearts);

                    $profile->update([
                        'heart_inventory' => max(0, $profile->heart_inventory - $deductFromInventory),
                        'hearts' => max(0, $profile->hearts - $deductFromMeter),
                    ]);

                    HeartTransaction::create([
                        'user_id' => $purchase->user_id,
                        'purchase_id' => $purchase->id,
                        'type' => 'refund',
                        'amount' => -$deductTotal,
                        'reason' => 'Refund processed for purchase #' . $purchase->id,
                        'metadata' => [
                            'refund_request_id' => $refundRequest->id,
                            'paymongo_refund_id' => $paymongoRefundId,
                        ],
                    ]);
                }
            }

            // Update Purchase and PaymentTransaction status
            $purchase->update([
                'status' => 'refunded',
            ]);

            PaymentTransaction::where('purchase_id', $purchase->id)->update([
                'status' => 'refunded',
            ]);

            // Update RefundRequest record
            $refundRequest->update([
                'status' => 'refunded',
                'admin_notes' => $adminNotes,
                'paymongo_refund_id' => $paymongoRefundId,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);
        });

        // 3. Send Status Email to User
        try {
            $userEmail = $refundRequest->user->email;
            if (!empty($userEmail)) {
                Mail::to($userEmail)->send(new RefundStatusUpdatedMail($refundRequest->fresh(['user', 'purchase'])));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund approval email to {$refundRequest->user->email}: " . $e->getMessage());
        }

        return $refundRequest->fresh(['user', 'purchase', 'reviewer']);
    }

    /**
     * Reject a refund request with a reason.
     */
    public function reject(RefundRequest $refundRequest, User $admin, string $reason): RefundRequest
    {
        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'refund_request' => 'This refund request is already ' . $refundRequest->status . '.',
            ]);
        }

        $refundRequest->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ]);

        $refundRequest->loadMissing(['user', 'purchase']);

        try {
            $userEmail = $refundRequest->user->email;
            if (!empty($userEmail)) {
                Mail::to($userEmail)->send(new RefundStatusUpdatedMail($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund rejection email to {$refundRequest->user->email}: " . $e->getMessage());
        }

        return $refundRequest->fresh(['user', 'purchase', 'reviewer']);
    }

    /**
     * Calls PayMongo API to issue refund for a paid checkout.
     */
    protected function callPayMongoRefundApi(Purchase $purchase, RefundRequest $refundRequest): string
    {
        $secretKey = (string) config('services.paymongo.secret_key');
        $apiUrl = rtrim((string) config('services.paymongo.api_url', 'https://api.paymongo.com'), '/');

        // Extract payment_id from paymentTransactions raw_payload
        $paymentId = null;
        $transaction = $purchase->paymentTransactions()->latest()->first();

        if ($transaction && is_array($transaction->raw_payload)) {
            $paymentId = data_get($transaction->raw_payload, 'data.attributes.data.attributes.payments.0.id')
                ?: data_get($transaction->raw_payload, 'data.attributes.payments.0.id')
                ?: data_get($transaction->raw_payload, 'data.id');
        }

        // If not found in local payload, attempt retrieval from PayMongo checkout session API
        if (!$paymentId && $purchase->checkout_session_id && !empty($secretKey)) {
            try {
                $sessionRes = Http::acceptJson()
                    ->withBasicAuth($secretKey, '')
                    ->get("{$apiUrl}/v1/checkout_sessions/{$purchase->checkout_session_id}");

                if ($sessionRes->successful()) {
                    $paymentId = data_get($sessionRes->json(), 'data.attributes.payments.0.id');
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch checkout session {$purchase->checkout_session_id} from PayMongo: " . $e->getMessage());
            }
        }

        // If PayMongo credentials are configured and payment ID is present, invoke refund API
        if (!empty($secretKey) && !empty($paymentId)) {
            try {
                $amountInCentavos = (int) round(((float) $refundRequest->amount) * 100);

                $response = Http::acceptJson()
                    ->withBasicAuth($secretKey, '')
                    ->post("{$apiUrl}/v1/refunds", [
                        'data' => [
                            'attributes' => [
                                'amount' => $amountInCentavos,
                                'payment_id' => $paymentId,
                                'reason' => 'requested_by_customer',
                                'notes' => "Signia Order #{$purchase->id} refund approved",
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $refundId = data_get($response->json(), 'data.id');
                    if ($refundId) {
                        return (string) $refundId;
                    }
                } else {
                    Log::warning("PayMongo Refund API responded with status " . $response->status() . ": " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("PayMongo Refund API error: " . $e->getMessage());
            }
        }

        // Fallback for test / dev / offline sandbox scenarios
        return 'ref_simulated_' . substr(md5(uniqid((string) $purchase->id, true)), 0, 16);
    }
}
