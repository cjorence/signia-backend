<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        Achievement::firstOrCreate([
            'name' => 'First Steps',
        ], [
            'description' => 'Complete your first sign.',
            'condition_type' => 'completed_signs',
            'condition_value' => 1,
        ]);

        Achievement::firstOrCreate([
            'name' => 'Rising Learner',
        ], [
            'description' => 'Earn 100 total XP.',
            'condition_type' => 'total_xp',
            'condition_value' => 100,
        ]);

        Achievement::firstOrCreate([
            'name' => 'Quest Starter',
        ], [
            'description' => 'Complete your first quest.',
            'condition_type' => 'completed_quests',
            'condition_value' => 1,
        ]);

        Achievement::firstOrCreate([
            'name' => 'Quiz Master',
        ], [
            'description' => 'Score at least 5 points in a quiz.',
            'condition_type' => 'quiz_score',
            'condition_value' => 5,
        ]);

        Achievement::firstOrCreate([
            'name' => 'Sharp Signer',
        ], [
            'description' => 'Reach 80% gesture accuracy.',
            'condition_type' => 'gesture_accuracy',
            'condition_value' => 80,
        ]);

        Achievement::firstOrCreate([
            'name' => 'Consistent Learner',
        ], [
            'description' => 'Reach a 7 day streak.',
            'condition_type' => 'streak',
            'condition_value' => 7,
        ]);
    }
}