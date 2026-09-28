<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('levels', 'question_prompt')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->string('question_prompt')->nullable()->after('difficulty')
                    ->comment('Custom quiz question prompt for this category (e.g. What letter is shown?)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('levels', 'question_prompt')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->dropColumn('question_prompt');
            });
        }
    }
};
