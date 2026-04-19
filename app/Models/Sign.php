<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sign extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'name',
        'fsl_name',
        'description',
        'image_url',
        'video_url',
        'model_label',
        'difficulty',
        'xp_reward',
    ];

    protected function casts(): array
    {
        return [
            'xp_reward' => 'integer',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}