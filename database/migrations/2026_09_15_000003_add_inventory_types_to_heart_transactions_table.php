<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE heart_transactions MODIFY type ENUM('lost', 'regen', 'purchase', 'admin_grant', 'refund', 'inventory_credit', 'inventory_refill') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('heart_transactions', function (Blueprint $table) {
                $table->enum('type', [
                    'lost',
                    'regen',
                    'purchase',
                    'admin_grant',
                    'refund',
                    'inventory_credit',
                    'inventory_refill',
                ])->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('heart_transactions')
                ->where('type', 'inventory_credit')
                ->update(['type' => 'purchase']);

            DB::table('heart_transactions')
                ->where('type', 'inventory_refill')
                ->update(['type' => 'regen']);

            DB::statement("ALTER TABLE heart_transactions MODIFY type ENUM('lost', 'regen', 'purchase', 'admin_grant', 'refund') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('heart_transactions', function (Blueprint $table) {
                $table->enum('type', [
                    'lost',
                    'regen',
                    'purchase',
                    'admin_grant',
                    'refund',
                ])->change();
            });
        }
    }
};
