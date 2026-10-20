<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signs', 'is_motion')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->boolean('is_motion')->default(false)->after('model_label');
            });
        }

        // List of signs that require motion/dynamic gesture
        $motionSigns = [
            'J', 'Z', 'APRIL', 'AUGUST', 'AUNTIE', 'BEER', 'BLACK', 'BLIND', 'BLUE', 'BOY',
            'BREAD', 'BROWN', 'CHICKEN', 'COFFEE', 'COLD', 'CORRECT', 'COUSIN', 'CRAB',
            'DARK', 'DAUGHTER', 'DEAF DLIND', 'DEAF BLIND', 'DEAF', 'DECEMBER', "DON'T KNOW",
            "DON'T UNDERSTAND", 'EGG', 'EIGHT', 'FAST', 'FATHER', 'FEBRUARY', 'FISH', 'FIVE',
            'FOUR', 'FRIDAY', 'GIRL', 'GOOD AFTERNOON', 'GOOD EVENING', 'GOOD MORNING',
            'GRAND FATHER', 'GRAND MOTHER', 'GRAY', 'HARD OF HEARING', 'HELLO', 'HOT',
            'HOW ARE YOU', "I'M FINE", 'JANUARY', 'JUICE', 'JULY', 'JUNE', 'KNOW', 'LIGHT',
            'LONGANISA', 'MAN', 'MARCH', 'MARRIED', 'MAY', 'MEAT', 'MILK', 'MONDAY', 'MOTHER',
            'NICE TO MEET YOU', 'NINE', 'NO SUGAR', 'NO', 'NOVEMBER', 'OCTOBER', 'ONE', 'ORANGE',
            'PARENT', 'PINK', 'RED', 'RICE', 'SATURDAY', 'SEE YOU TOMORROW', 'SEPTEMBER', 'SEVEN',
            'SHRIMP', 'SIX', 'SLOW', 'SON', 'SPAGHETTI', 'SUGAR', 'SUNDAY', 'TEA', 'TEN',
            'THANK YOU', 'THREE', 'THURSDAY', 'TODAY', 'TOMORROW', 'TUESDAY', 'TWO', 'UNCLE',
            'UNDERSTAND', 'VIOLET', 'WEDNESDAY', 'WHEELCHAIR PERSON', 'WHITE', 'WINE', 'WOMAN',
            'WRONG', 'YELLOW', 'YES', 'YESTERDAY', "YOURE WELCOME", "YOU'RE WELCOME"
        ];

        // Seed is_motion = true for all matching signs in the database
        DB::table('signs')
            ->whereIn(DB::raw('UPPER(TRIM(name))'), $motionSigns)
            ->orWhereIn(DB::raw('UPPER(TRIM(fsl_name))'), $motionSigns)
            ->update(['is_motion' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('signs', 'is_motion')) {
            Schema::table('signs', function (Blueprint $table) {
                $table->dropColumn('is_motion');
            });
        }
    }
};

