<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'read_ids',
        'dismissed_ids',
        'all_read_at',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'read_ids' => 'array',
            'dismissed_ids' => 'array',
            'all_read_at' => 'integer',
            'cleared_at' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

