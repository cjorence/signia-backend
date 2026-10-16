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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        if (in_array($purchase->product_type, ['heart', 'hearts'], true)) {
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
            if (! empty($user->email)) {
                Mail::to($user->email)->send(new RefundRequestSubmittedMail($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund request confirmation to user {$user->email}: ".$e->getMessage());
        }

        // Send alert to Dedicated System / Admin Support Email
        try {
            $adminEmail = env('ADMIN_SUPPORT_EMAIL') ?: config('mail.from.address');
            if (! empty($adminEmail)) {
                Mail::to($adminEmail)->send(new RefundRequestAdminAlertMail($refundRequest));
            }
        } catch (\Throwable $e) {
            Log::warning("Could not send refund alert to admin {$adminEmail}: ".$e->getMessage());
        }

        return $refundRequest;
    }

    public function approve(RefundRequest $refundRequest, User $admin, ?string $adminNotes = null, ?float $approvedAmount = null): RefundRequest
    {
        $result = Cache::lock('refund-action-'.$refundRequest->id, 90)->get(function () use ($refundRequest, $admin, $adminNotes, $approvedAmount) {
            // Persist the attempt key before contacting the provider, so a crash cannot lose it.
            $prepared = DB::transaction(function () use ($refundRequest, $admin, $adminNotes, $approvedAmount) {
                $request = RefundRequest::whereKey($refundRequest->id)->lockForUpdate()->firstOrFail();
                $retry = $request->status === 'approved' && in_array($request->provider_status, ['unknown', 'failed'], true);
                if ($request->status !== 'pending' && ! $retry) {
                    throw ValidationException::withMessages(['refund' => 'This request has already been reviewed. Refresh to see its status.']);
                }
                $purchase = Purchase::whereKey($request->purchase_id)->lockForUpdate()->firstOrFail();
                if ($purchase->status !== 'paid') {
                    throw ValidationException::withMessages(['refund' => 'Only paid purchases can be refunded.']);
                }

                // If approvedAmount is provided (e.g. 20% cut-off), validate and apply it
                if ($approvedAmount !== null) {
                    if ($approvedAmount <= 0 || $approvedAmount > (float) $purchase->amount) {
                        throw ValidationException::withMessages(['refund' => 'Invalid refund amount specified.']);
                    }
                    $request->amount = round($approvedAmount, 2);
                }

                if ($request->provider_status === 'unknown' && $request->reviewed_at?->lt(now()->subHours(23))) {
                    throw ValidationException::withMessages(['refund' => 'The previous refund could not be confirmed. Check the payment provider before taking further action.']);
                }
                $original = $request->only(['status', 'provider_status', 'admin_notes', 'reviewed_at', 'reviewed_by', 'amount']);
                $reviewedAt = $request->provider_status === 'unknown' ? $request->reviewed_at : now();
                if (! $request->provider_request_key || $request->provider_status === 'failed') {
                    $request->provider_request_key = (string) Str::uuid();
                }
                $request->fill(['status' => 'approved', 'provider_status' => 'unknown', 'admin_notes' => $adminNotes,
                    'reviewed_at' => $reviewedAt, 'reviewed_by' => $admin->id])->save();

                return [$request, $purchase, $original];
            });
            [$request, $purchase, $original] = $prepared;
            try {
                $provider = $this->callPayMongoRefundApi($purchase, $request);
            } catch (ValidationException $e) {
                $request->update($original);
                throw $e;
            }
            $result = DB::transaction(function () use ($request, $provider) {
                $locked = RefundRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                $purchase = Purchase::whereKey($locked->purchase_id)->lockForUpdate()->firstOrFail();
                $locked->update(['provider_status' => $provider['status'], 'paymongo_refund_id' => $provider['id']]);
                if ($provider['status'] === 'succeeded') {
                    $this->completeRefund($locked, $purchase);
                }

                return $locked;
            });
            $this->sendStatusEmail($result);

            return $result->fresh(['user', 'purchase', 'reviewer']);
        });
        if (! $result) {
            throw ValidationException::withMessages(['refund' => 'This refund is already being processed. Please wait and refresh.']);
        }

        return $result;
    }

    private function completeRefund(RefundRequest $refundRequest, Purchase $purchase): void
    {
        if ($refundRequest->status === 'refunded') {
            return;
        }
        $paymongoRefundId = $refundRequest->paymongo_refund_id;
        // Revert Story Unlock if applicable
        if ($purchase->product_type === 'story' && $purchase->story_id) {
            UserStoryUnlock::where('user_id', $purchase->user_id)
                ->where('story_id', $purchase->story_id)
                ->delete();
        }

        // Revert Hearts if applicable
        if (in_array($purchase->product_type, ['heart', 'hearts'], true)) {
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
                    'next_heart_at' => $deductFromMeter > 0
                        ? ($profile->next_heart_at ?? now()->addMinutes(HeartService::REFRESH_MINUTES))
                        : $profile->next_heart_at,
                ]);

                HeartTransaction::create([
                    'user_id' => $purchase->user_id,
                    'purchase_id' => $purchase->id,
                    'type' => 'refund',
                    'amount' => -$deductTotal,
                    'reason' => 'Refund processed for purchase #'.$purchase->id,
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

        $refundRequest->update(['status' => 'refunded', 'provider_status' => 'succeeded']);
    }

    public function reject(RefundRequest $refundRequest, User $admin, string $reason): RefundRequest
    {
        $result = DB::transaction(function () use ($refundRequest, $admin, $reason) {
            $request = RefundRequest::whereKey($refundRequest->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => 'This request has already been reviewed. Refresh to see its status.']);
            }
            $request->update(['status' => 'rejected', 'admin_notes' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $admin->id]);

            return $request;
        });
        $this->sendStatusEmail($result);

        return $result->fresh(['user', 'purchase', 'reviewer']);
    }

    private function sendStatusEmail(RefundRequest $request): void
    {
        $request->loadMissing(['user', 'purchase']);
        $status = 'not_configured';
        try {
            if (! empty($request->user?->email)) {
                Mail::to($request->user->email)->send(new RefundStatusUpdatedMail($request));
                // Transport acceptance is not proof of inbox delivery.
                $status = in_array(config('mail.default'), ['log', 'array'], true) ? 'not_configured' : 'sent';
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            Log::warning('Refund status email failed', ['refund_request_id' => $request->id, 'error' => $e->getMessage()]);
        }
        $request->update(['email_delivery_status' => $status]);
    }

    /** Reconcile only this caller's visible approved requests; never initiate new refunds here. */
    public function syncProcessingRefunds(?int $userId = null, int $limit = 20, int $timeoutSeconds = 8): void
    {
        $query = RefundRequest::where('status', 'approved')->whereNotNull('paymongo_refund_id')
            ->whereIn('provider_status', ['pending', 'processing']);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $limit = max(1, min(20, $limit));
        $cursorKey = 'refund-sync-cursor-'.($userId ?? 'all');
        $cursor = (int) Cache::get($cursorKey, 0);
        $batch = (clone $query)->where('id', '>', $cursor)->orderBy('id')->limit($limit)->get();
        if ($batch->isEmpty() && $cursor > 0) {
            $batch = (clone $query)->orderBy('id')->limit($limit)->get();
        }
        foreach ($batch as $request) {
            // Advance even if this request is still pending or throttled, so later refunds get a turn.
            Cache::put($cursorKey, $request->id, now()->addDay());
            if (! Cache::add('refund-sync-'.$request->id, true, 10)) {
                continue;
            }
            try {
                $key = (string) config('services.paymongo.secret_key');
                if ($key === '') {
                    continue;
                }
                $url = rtrim((string) config('services.paymongo.api_url', 'https://api.paymongo.com'), '/');
                $response = Http::acceptJson()->withBasicAuth($key, '')->timeout(max(1, $timeoutSeconds))
                    ->get($url.'/v1/refunds/'.$request->paymongo_refund_id);
                if (! $response->successful() || data_get($response->json(), 'data.id') !== $request->paymongo_refund_id) {
                    continue;
                }
                $status = data_get($response->json(), 'data.attributes.status');
                if (! in_array($status, ['succeeded', 'failed'], true)) {
                    continue;
                }
                $updated = DB::transaction(function () use ($request, $status) {
                    $locked = RefundRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'approved' || ! in_array($locked->provider_status, ['pending', 'processing'], true)) {
                        return null;
                    }
                    $locked->update(['provider_status' => $status]);
                    if ($status === 'succeeded') {
                        $purchase = Purchase::whereKey($locked->purchase_id)->lockForUpdate()->firstOrFail();
                        $this->completeRefund($locked, $purchase);
                    }

                    return $locked;
                });
                if ($updated) {
                    $this->sendStatusEmail($updated);
                }
            } catch (\Throwable $e) {
                Log::warning('Refund reconciliation failed', ['refund_request_id' => $request->id, 'error' => $e->getMessage()]);
            }
        }
    }

    protected function callPayMongoRefundApi(Purchase $purchase, RefundRequest $request): array
    {
        $secretKey = (string) config('services.paymongo.secret_key');
        if ($secretKey === '') {
            throw ValidationException::withMessages(['refund' => 'Refund processing is not configured. No refund was issued.']);
        }
        $apiUrl = rtrim((string) config('services.paymongo.api_url', 'https://api.paymongo.com'), '/');
        $transaction = $purchase->paymentTransactions()->latest()->first();
        $payload = $transaction?->raw_payload ?? [];
        $paymentId = data_get($payload, 'data.attributes.data.attributes.payments.0.id')
            ?: data_get($payload, 'data.attributes.payments.0.id');
        $directId = data_get($payload, 'data.id');
        if (! $paymentId && is_string($directId) && str_starts_with($directId, 'pay_')) {
            $paymentId = $directId;
        }
        if (! $paymentId && $purchase->checkout_session_id) {
            try {
                $session = Http::acceptJson()->withBasicAuth($secretKey, '')->timeout(10)
                    ->get($apiUrl.'/v1/checkout_sessions/'.$purchase->checkout_session_id);
                if ($session->successful()) {
                    $paymentId = data_get($session->json(), 'data.attributes.payments.0.id');
                }
            } catch (\Throwable $e) {
                Log::warning('Refund payment lookup failed', ['purchase_id' => $purchase->id]);
            }
        }
        if (! is_string($paymentId) || ! str_starts_with($paymentId, 'pay_')) {
            throw ValidationException::withMessages(['refund' => 'The original payment could not be found. No refund was issued.']);
        }
        try {
            $response = Http::acceptJson()->withBasicAuth($secretKey, '')->timeout(20)
                ->withHeaders(['Idempotency-Key' => $request->provider_request_key])
                ->post($apiUrl.'/v1/refunds', ['data' => ['attributes' => [
                    'amount' => (int) round(((float) $request->amount) * 100),
                    'payment_id' => $paymentId,
                    'reason' => 'requested_by_customer',
                    'notes' => 'Signia refund request #'.$request->id,
                ]]]);
        } catch (\Throwable $e) {
            return ['id' => null, 'status' => 'unknown'];
        }
        if ($response->clientError()) {
            throw ValidationException::withMessages(['refund' => 'The payment provider did not accept this refund. Check the payment before trying again.']);
        }
        $id = data_get($response->json(), 'data.id');
        $status = data_get($response->json(), 'data.attributes.status');
        if (! $response->successful() || ! is_string($id) || ! str_starts_with($id, 'ref_') || ! in_array($status, ['pending', 'processing', 'succeeded', 'failed'], true)) {
            return ['id' => null, 'status' => 'unknown'];
        }

        return ['id' => $id, 'status' => $status];
    }
}
