<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_story_progress', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedInteger('current_step')->default(0)->after('progress_percent');
            $table->unsignedInteger('total_steps')->default(0)->after('current_step');
            $table->json('save_state')->nullable()->after('total_steps');
            $table->timestamp('last_played_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_story_progress', function (Blueprint $table) {
            $table->dropColumn([
                'progress_percent',
                'current_step',
                'total_steps',
                'save_state',
                'last_played_at',
            ]);
        });
    }
};
