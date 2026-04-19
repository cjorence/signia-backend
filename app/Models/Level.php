<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'order',
        'required_xp',
    ];

    protected function casts(): array
    {
        return [
            'order'       => 'integer',
            'required_xp' => 'integer',
        ];
    }

    // ========================
    // RELATIONSHIPS
    // ========================

    public function signs(): HasMany
    {
        return $this->hasMany(Sign::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }
}