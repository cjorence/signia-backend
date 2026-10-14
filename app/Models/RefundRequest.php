<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purchase_id',
        'reason',
        'explanation',
        'amount',
        'currency',
        'status',
        'admin_notes',
        'paymongo_refund_id',
        'reviewed_at',
        'reviewed_by',
        'provider_status',
        'provider_request_key',
        'email_delivery_status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
