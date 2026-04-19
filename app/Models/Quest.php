<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'name',
        'description',
    ];

    // ========================
    // RELATIONSHIPS
    // ========================

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function userQuests(): HasMany
    {
        return $this->hasMany(UserQuest::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_quests')
                    ->withPivot('status')
                    ->withTimestamps();
    }
}