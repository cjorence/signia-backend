<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotificationSetting extends Model
{
    protected $fillable = ['id', 'preferences'];

    protected $casts = ['preferences' => 'array'];
}
