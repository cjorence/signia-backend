<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->unsignedInteger('hearts')->default(5)->after('streak');
            $table->unsignedInteger('max_hearts')->default(5)->after('hearts');
            $table->timestamp('next_heart_at')->nullable()->after('max_hearts');
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table) {
            $table->dropColumn(['hearts', 'max_hearts', 'next_heart_at']);
        });
    }
};