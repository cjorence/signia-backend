<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_active_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     * 'hashed' cast auto-hashes password on assignment — no Hash::make() needed.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at'    => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function playerProfile(): HasOne
    {
        return $this->hasOne(PlayerProfile::class);
    }

        public function userQuests(): HasMany
    {
        return $this->hasMany(UserQuest::class);
    }

    public function quests(): BelongsToMany
    {
        return $this->belongsToMany(Quest::class, 'user_quests')
                    ->withPivot('status')
                    ->withTimestamps();
    }


    // ========================
    // HELPERS
    // ========================

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}