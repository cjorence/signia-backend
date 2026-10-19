<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version', 40);
            $table->string('privacy_version', 40);
            $table->timestamp('accepted_at');
            $table->string('method', 20);
            $table->unique(['user_id', 'terms_version', 'privacy_version'], 'legal_acceptances_user_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
