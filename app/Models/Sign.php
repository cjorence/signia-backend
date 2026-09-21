<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_id',
        'name',
        'fsl_name',
        'description',
        'image_url',
        'video_url',
        'video_type',
        'video_start',
        'video_end',
        'difficulty',
        'xp_reward',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'xp_reward' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
