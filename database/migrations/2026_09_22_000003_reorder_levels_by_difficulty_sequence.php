<?php

use App\Models\Level;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $orderedLevels = [
            // Easy
            'FSL Alphabet'       => 1,
            'FSL Numbers'        => 2,

            // Medium
            'FSL Greetings'      => 3,
            'FSL Survival'       => 4,
            'FSL Calendar'       => 5,
            'FSL Days'           => 6,
            'FSL Family'         => 7,
            'FSL Relationships'  => 8,
            'FSL Colors'         => 9,
            'FSL Food'           => 10,
            'FSL Drinks'         => 11,
            'FSL Pronouns'       => 12,
            'FSL Communication'  => 13,

            // Hard
            'Emergency Signs'    => 14,
            'FSL Conversational' => 15,
        ];

        foreach ($orderedLevels as $name => $order) {
            Level::where('name', $name)->update(['order' => $order]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
