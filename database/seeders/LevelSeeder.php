<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        // Demo seed data is additive. Existing administrator-managed levels are
        // intentionally never renamed, repurposed, or overwritten here.
        Level::firstOrCreate(
            ['name' => 'FSL Alphabet'],
            [
                'name' => 'FSL Alphabet',
                'description' => 'Learn basic Filipino Sign Language fingerspelling letters from A to Z.',
                'difficulty' => 'easy',
                'order' => 1,
                'required_xp' => 0,
            ]
        );

        Level::firstOrCreate(
            ['name' => 'FSL Numbers'],
            [
                'name' => 'FSL Numbers',
                'description' => 'Count and sign numbers from 1 to 10 in Filipino Sign Language.',
                'difficulty' => 'easy',
                'order' => 2,
                'required_xp' => 100,
            ]
        );

        Level::firstOrCreate(
            ['name' => 'FSL Greetings'],
            [
                'description' => 'Common polite expressions and daily greetings used in the Filipino Deaf community.',
                'difficulty' => 'medium',
                'order' => 3,
                'required_xp' => 250,
            ]
        );
    }
}
