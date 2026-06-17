<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('condition_type', [
                'total_xp',
                'completed_signs',
                'completed_quests',
                'quiz_score',
                'gesture_accuracy',
                'streak',
            ]);
            $table->unsignedInteger('condition_value');
            $table->timestamps();

            $table->index('condition_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};