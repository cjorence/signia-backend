<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Import existing preferences before a deployment clears the old cache.
        $preferences = ['newUserAlerts' => true, 'purchaseAlerts' => true, 'trainingAlerts' => true];
        $cached = Cache::get('admin_notification_settings', []);
        foreach ($preferences as $key => $default) {
            if (is_array($cached) && array_key_exists($key, $cached)) {
                $preferences[$key] = (bool) $cached[$key];
            }
        }

        Schema::create('admin_notification_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('preferences');
            $table->timestamps();
        });

        // Keep delivery receipts even if an administrator dismisses an alert.
        Schema::create('admin_notification_events', function (Blueprint $table) {
            $table->string('event_key', 100)->primary();
            $table->timestamp('created_at');
        });

        DB::table('admin_notification_settings')->insert([
            'id' => 1,
            'preferences' => json_encode($preferences, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_events');
        Schema::dropIfExists('admin_notification_settings');
    }
};
