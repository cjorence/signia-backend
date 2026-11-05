<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable();
            $table->string('admin_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'admin_name']);
        });
    }
};
