<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('sign_id')
                  ->constrained('signs')
                  ->onDelete('cascade');
            $table->foreignId('level_id')
                  ->constrained('levels')
                  ->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('attempts')->default(0);
            $table->decimal('best_confidence', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['user_id', 'sign_id']);
            $table->index('user_id');
            $table->index('level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress');
    }
};