<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar',
        'current_level',
        'total_xp',
        'streak',
        'last_played_date',
        'hearts',
        'max_hearts',
        'heart_inventory',
        'next_heart_at',
    ];

    protected function casts(): array
    {
        return [
            'current_level'    => 'integer',
            'total_xp'         => 'integer',
            'streak'           => 'integer',
            'last_played_date' => 'date',
            'hearts' => 'integer',
            'max_hearts' => 'integer',
            'heart_inventory' => 'integer',
            'next_heart_at' => 'datetime',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ========================
    // ACCESSORS
    // ========================

    /**
     * Compute active streak. If practice has not occurred today or yesterday, streak is 0.
     */
    public function getActiveStreakAttribute(): int
    {
        $streak = (int) ($this->streak ?? 0);
        if (! $this->last_played_date) {
            return 0;
        }

        $lastPlayed = $this->last_played_date->copy()->startOfDay();
        if (! $lastPlayed->isToday() && ! $lastPlayed->isYesterday()) {
            return 0;
        }

        return $streak;
    }
}
