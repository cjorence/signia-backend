<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gesture_logs', function (Blueprint $table) {
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
            $table->string('expected_sign');
            $table->string('predicted_sign');
            $table->decimal('confidence', 5, 2);
            $table->boolean('is_correct')->default(false);
            $table->decimal('attempt_duration', 8, 2)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('sign_id');
            $table->index('level_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gesture_logs');
    }
};