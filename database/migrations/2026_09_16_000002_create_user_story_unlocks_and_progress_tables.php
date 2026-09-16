<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_story_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->timestamp('unlocked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'story_id']);
            $table->index('user_id');
            $table->index('story_id');
        });

        Schema::create('user_story_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('story_id')->constrained('stories')->onDelete('cascade');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'story_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_story_progress');
        Schema::dropIfExists('user_story_unlocks');
    }
};
