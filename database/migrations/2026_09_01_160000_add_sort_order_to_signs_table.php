<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signs', 'sort_order')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('xp_reward');
                $table->index(['level_id', 'sort_order']);
            });
        }

        if (Schema::hasColumn('signs', 'sort_order')) {
            DB::statement('UPDATE signs SET sort_order = id WHERE sort_order = 0');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signs', 'sort_order')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->dropIndex(['level_id', 'sort_order']);
                $table->dropColumn('sort_order');
            });
        }
    }
};
