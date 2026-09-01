<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            if (!Schema::hasColumn('levels', 'difficulty')) {
                $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy')->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            if (Schema::hasColumn('levels', 'difficulty')) {
                $table->dropColumn('difficulty');
            }
        });
    }
};
