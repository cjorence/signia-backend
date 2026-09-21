<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signs', function (Blueprint $table) {
            // Drop model_label — prediction mode is now owned by levels.mode_level
            $table->dropColumn('model_label');

            // video_type distinguishes between local file paths and YouTube URLs
            $table->enum('video_type', ['local', 'youtube'])->default('local')->after('video_url')
                ->comment('Source type of the video: local file or YouTube embed');

            // video_start / video_end as M:SS strings (e.g. "0:09", "1:23")
            $table->string('video_start', 10)->nullable()->after('video_type')
                ->comment('Video clip start time in M:SS format, e.g. 0:09');

            $table->string('video_end', 10)->nullable()->after('video_start')
                ->comment('Video clip end time in M:SS format, e.g. 1:23');
        });
    }

    public function down(): void
    {
        Schema::table('signs', function (Blueprint $table) {
            $table->string('model_label')->nullable()->after('video_url');
            $table->dropColumn(['video_type', 'video_start', 'video_end']);
        });
    }
};
