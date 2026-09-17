<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('chapter_number')->default(1);
            $table->unsignedInteger('order')->default(1);
            $table->boolean('is_free')->default(false);
            $table->decimal('price', 10, 2)->default(49.00);
            $table->string('currency', 3)->default('PHP');
            $table->unsignedInteger('episodes')->default(3);
            $table->string('icon')->default('MapPin');
            $table->string('color')->default('from-violet-500 to-fuchsia-500');
            $table->string('cover_url')->nullable();
            $table->string('file_name')->nullable();
            $table->json('required_lesson_ids')->nullable();
            $table->timestamps();

            $table->index('chapter_number');
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
