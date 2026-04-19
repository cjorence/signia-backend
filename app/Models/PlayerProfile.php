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
    ];

    protected function casts(): array
    {
        return [
            'current_level'    => 'integer',
            'total_xp'         => 'integer',
            'streak'           => 'integer',
            'last_played_date' => 'date',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}