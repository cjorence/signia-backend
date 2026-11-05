<?php

namespace App\Support;

use Illuminate\Support\Str;

class DisplayName
{
    public static function package(?string $key): string
    {
        return match ($key) {
            'small_hearts' => 'Small Hearts Pack',
            'medium_hearts' => 'Medium Hearts Pack',
            'large_hearts' => 'Large Hearts Pack',
            null, '' => 'Hearts Pack',
            default => Str::headline($key),
        };
    }
}
