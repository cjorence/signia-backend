<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'chapter_number',
        'order',
        'is_free',
        'price',
        'currency',
        'episodes',
        'icon',
        'color',
        'cover_url',
        'file_name',
        'required_lesson_ids',
    ];

    protected $casts = [
        'chapter_number' => 'integer',
        'order' => 'integer',
        'is_free' => 'boolean',
        'price' => 'decimal:2',
        'episodes' => 'integer',
        'required_lesson_ids' => 'array',
    ];

    public function unlocks(): HasMany
    {
        return $this->hasMany(UserStoryUnlock::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserStoryProgress::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function isUnlockedFor(?User $user): bool
    {
        if ($this->is_free || $this->chapter_number === 1) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $this->unlocks()->where('user_id', $user->id)->exists();
    }

    public function isCompletedFor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->progress()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();
    }
}
