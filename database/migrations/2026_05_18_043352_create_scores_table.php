<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('level_id')
                  ->constrained('levels')
                  ->onDelete('cascade');
            $table->unsignedInteger('score')->default(0);
            $table->decimal('time_taken', 8, 2)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};