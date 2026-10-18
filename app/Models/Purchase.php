<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_type',
        'story_id',
        'package_key',
        'quantity',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_reference',
        'checkout_session_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        // Pending payments must still grant ownership when a chapter is archived.
        return $this->belongsTo(Story::class)->withTrashed();
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function heartTransactions(): HasMany
    {
        return $this->hasMany(HeartTransaction::class);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function latestRefundRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RefundRequest::class)->latestOfMany();
    }

    public function getPaymongoPaymentIdAttribute(): ?string
    {
        $transaction = $this->relationLoaded('paymentTransactions')
            ? $this->paymentTransactions->last()
            : $this->paymentTransactions()->latest()->first();

        $payload = $transaction?->raw_payload ?? [];
        $paymentId = data_get($payload, 'data.attributes.data.attributes.payments.0.id')
            ?: data_get($payload, 'data.attributes.payments.0.id')
            ?: data_get($payload, 'data.attributes.data.attributes.payment_intent.attributes.payments.0.id');

        if (! $paymentId) {
            $directId = data_get($payload, 'data.id');
            if (is_string($directId) && str_starts_with($directId, 'pay_')) {
                $paymentId = $directId;
            }
        }

        if (! $paymentId && is_string($this->provider_reference) && str_starts_with($this->provider_reference, 'pay_')) {
            $paymentId = $this->provider_reference;
        }

        return is_string($paymentId) ? $paymentId : null;
    }

    public function getPaymongoRefundIdAttribute(): ?string
    {
        $refund = $this->relationLoaded('latestRefundRequest')
            ? $this->latestRefundRequest
            : $this->latestRefundRequest()->first();

        return $refund?->paymongo_refund_id;
    }
}
