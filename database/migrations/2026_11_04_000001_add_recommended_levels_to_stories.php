<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->json('recommended_level_ids')->nullable();
        });

        // Store IDs for installed curriculum so later category renames preserve
        // the recommendations. New/unseeded installations use the same defaults
        // in StoryLessonService until their first admin save.
        $levels = DB::table('levels')->whereNull('deleted_at')->pluck('id', 'name');
        foreach (config('story_lessons', []) as $slug => $names) {
            $ids = collect($names)->map(fn ($name) => $levels[$name] ?? null)->filter()->values()->all();
            if ($ids !== []) {
                DB::table('stories')->where('slug', $slug)->whereNull('recommended_level_ids')
                    ->update(['recommended_level_ids' => json_encode($ids)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('recommended_level_ids');
        });
    }
};
