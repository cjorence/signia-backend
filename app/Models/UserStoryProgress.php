<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStoryProgress extends Model
{
    use HasFactory;

    protected $table = 'user_story_progress';

    protected $fillable = [
        'user_id',
        'story_id',
        'status',
        'progress_percent',
        'current_step',
        'total_steps',
        'save_state',
        'completed_at',
        'last_played_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'last_played_at' => 'datetime',
        'progress_percent' => 'integer',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'save_state' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
