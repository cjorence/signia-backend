<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestureLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sign_id',
        'level_id',
        'expected_sign',
        'predicted_sign',
        'confidence',
        'is_correct',
        'attempt_duration',
    ];

    protected function casts(): array
    {
        return [
            'confidence'       => 'decimal:2',
            'is_correct'       => 'boolean',
            'attempt_duration' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sign(): BelongsTo
    {
        return $this->belongsTo(Sign::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}