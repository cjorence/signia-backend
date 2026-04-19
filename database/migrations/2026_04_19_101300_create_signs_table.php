<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')
                  ->constrained('levels')
                  ->onDelete('cascade');
            $table->string('name');
            $table->string('fsl_name')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->string('model_label');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->unsignedInteger('xp_reward')->default(10);
            $table->timestamps();

            $table->index('level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signs');
    }
};