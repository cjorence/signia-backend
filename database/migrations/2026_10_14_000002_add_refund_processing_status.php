<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->string('provider_status')->nullable();
            $table->string('provider_request_key')->nullable();
            $table->string('email_delivery_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', fn (Blueprint $table) => $table->dropColumn([
            'provider_status', 'provider_request_key', 'email_delivery_status',
        ]));
    }
};
