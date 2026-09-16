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
            DB::statement("ALTER TABLE purchases MODIFY product_type VARCHAR(32) NOT NULL DEFAULT 'hearts'");
        }

        Schema::table('purchases', function (Blueprint $table) {
            if (DB::getDriverName() !== 'mysql') {
                $table->string('product_type', 32)->default('hearts')->change();
            }
            $table->foreignId('story_id')->nullable()->after('product_type')->constrained('stories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['story_id']);
            $table->dropColumn('story_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchases MODIFY product_type ENUM('hearts') NOT NULL DEFAULT 'hearts'");
        }
    }
};
