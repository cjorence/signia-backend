<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            $table->timestamp('xp_awarded_at')->nullable()->after('best_confidence');
            $table->index('xp_awarded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            $table->dropIndex(['xp_awarded_at']);
            $table->dropColumn('xp_awarded_at');
        });
    }
};
