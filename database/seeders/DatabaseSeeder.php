<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Achievement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::updateOrCreate(
            ['email' => 'admin@signia.app'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $player = User::updateOrCreate(
            ['email' => 'player@signia.app'],
            [
                'name' => 'Player User',
                'password' => 'password',
                'role' => 'user',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $player->playerProfile()->firstOrCreate([], [
            'current_level' => 1,
            'total_xp'      => 0,
            'streak'        => 0,
            'hearts'        => 5,
        ]);

        $this->call([
            LevelSeeder::class,
            SignSeeder::class,
            AchievementSeeder::class,
            StorySeeder::class,
        ]);
        
    }
}
