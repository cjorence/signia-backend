<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signs', 'model_label')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->string('model_label')->nullable()->after('sort_order');
            });
        }

        if (Schema::hasColumn('levels', 'mode_level')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->dropColumn('mode_level');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('levels', 'mode_level')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->string('mode_level')->nullable()->after('required_xp');
            });
        }

        if (Schema::hasColumn('signs', 'model_label')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->dropColumn('model_label');
            });
        }
    }
};
