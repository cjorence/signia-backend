<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. FSL Alphabet
        // Look for existing alphabet level (or ID 1) to preserve any existing relations
        $alphabetLevel = Level::where('id', 1)
            ->orWhere('name', 'FSL Alphabet')
            ->orWhere('name', 'Level 1 - Alphabet Basics')
            ->first();

        if ($alphabetLevel) {
            $alphabetLevel->update([
                'name' => 'FSL Alphabet',
                'description' => 'Learn basic Filipino Sign Language fingerspelling letters from A to Z.',
                'difficulty' => 'easy',
                'order' => 1,
                'required_xp' => 0,
            ]);
        } else {
            $alphabetLevel = Level::create([
                'name' => 'FSL Alphabet',
                'description' => 'Learn basic Filipino Sign Language fingerspelling letters from A to Z.',
                'difficulty' => 'easy',
                'order' => 1,
                'required_xp' => 0,
            ]);
        }

        // Check if there is an empty duplicate level named 'FSL Alphabet' with a different ID
        $duplicateAlphabet = Level::where('name', 'FSL Alphabet')
            ->where('id', '!=', $alphabetLevel->id)
            ->first();

        // 2. FSL Numbers
        $numbersLevel = Level::where('name', 'FSL Numbers')->first();
        if (!$numbersLevel && $duplicateAlphabet && $duplicateAlphabet->signs()->count() === 0) {
            // Repurpose the duplicate level to prevent slug collision and preserve IDs
            $duplicateAlphabet->update([
                'name' => 'FSL Numbers',
                'description' => 'Count and sign numbers from 1 to 10 in Filipino Sign Language.',
                'difficulty' => 'easy',
                'order' => 2,
                'required_xp' => 0,
            ]);
            $numbersLevel = $duplicateAlphabet;
        } elseif (!$numbersLevel) {
            $numbersLevel = Level::create([
                'name' => 'FSL Numbers',
                'description' => 'Count and sign numbers from 1 to 10 in Filipino Sign Language.',
                'difficulty' => 'easy',
                'order' => 2,
                'required_xp' => 0,
            ]);
        } else {
            $numbersLevel->update([
                'description' => 'Count and sign numbers from 1 to 10 in Filipino Sign Language.',
                'difficulty' => 'easy',
                'order' => 2,
                'required_xp' => 0,
            ]);
        }

        // 3. FSL Greetings
        Level::updateOrCreate(
            ['name' => 'FSL Greetings'],
            [
                'description' => 'Common polite expressions and daily greetings used in the Filipino Deaf community.',
                'difficulty' => 'medium',
                'order' => 3,
                'required_xp' => 0,
            ]
        );
    }
}
