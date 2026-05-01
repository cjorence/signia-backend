<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Sign;
use Illuminate\Database\Eloquent\Collection;

class SignService
{
    /**
     * Get all signs for a specific level.
     */
    public function getSignsByLevel(Level $level): Collection
    {
        return $level->signs()->orderBy('id')->get();
    }

    /**
     * Get a single sign with its level loaded.
     */
    public function getSignDetail(Sign $sign): Sign
    {
        return $sign->load('level');
    }

    /**
     * Create a new sign.
     */
    public function createSign(array $data): Sign
    {
        $sign = Sign::create($data);

        return $sign->load('level');
    }

    /**
     * Update an existing sign.
     */
    public function updateSign(Sign $sign, array $data): Sign
    {
        $sign->update($data);

        return $sign->fresh()->load('level');
    }

    /**
     * Delete a sign.
     */
    public function deleteSign(Sign $sign): bool
    {
        return (bool) $sign->delete();
    }
}