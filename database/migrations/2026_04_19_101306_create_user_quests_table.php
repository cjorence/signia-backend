<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('quest_id')
                  ->constrained('quests')
                  ->onDelete('cascade');
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'quest_id']);
            $table->index('user_id');
            $table->index('quest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quests');
    }
};