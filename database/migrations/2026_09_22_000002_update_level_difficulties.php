<?php

use App\Models\Level;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $categoryDifficulties = [
            'FSL Alphabet'       => 'easy',
            'FSL Numbers'        => 'easy',
            'FSL Greetings'      => 'medium',
            'FSL Survival'       => 'medium',
            'FSL Calendar'       => 'medium',
            'FSL Days'           => 'medium',
            'FSL Family'         => 'medium',
            'FSL Relationships'  => 'medium',
            'FSL Colors'         => 'medium',
            'FSL Food'           => 'medium',
            'FSL Drinks'         => 'medium',
            'FSL Pronouns'       => 'medium',
            'FSL Communication'  => 'medium',
            'FSL Conversational' => 'hard',
        ];

        foreach ($categoryDifficulties as $name => $difficulty) {
            Level::where('name', $name)->update([
                'difficulty' => $difficulty,
            ]);
        }
    }

    public function down(): void
    {
        // No-op or revert to default
    }
};
