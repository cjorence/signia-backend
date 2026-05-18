<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progress extends Model
{
    use HasFactory;

    protected $table = 'progress';

    protected $fillable = [
        'user_id',
        'sign_id',
        'level_id',
        'is_completed',
        'attempts',
        'best_confidence',
    ];

    protected function casts(): array
    {
        return [
            'is_completed'    => 'boolean',
            'attempts'        => 'integer',
            'best_confidence' => 'decimal:2',
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