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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')
                ->constrained('quizzes')
                ->onDelete('cascade');
            $table->text('question_text');
            $table->enum('question_type', ['mcq', 'identification', 'gesture']);
            $table->foreignId('sign_id')
                ->nullable()
                ->constrained('signs')
                ->nullOnDelete();
            $table->string('correct_answer');
            $table->timestamps();

            $table->index('quiz_id');
            $table->index('sign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
