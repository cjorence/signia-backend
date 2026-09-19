<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\PlayerProfile;
use App\Models\Progress;
use App\Models\Sign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinisherSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_who_completed_all_signs_in_level_appears_as_finisher(): void
    {
        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet level',
            'difficulty' => 'easy',
            'order' => 1,
        ]);

        $sign1 = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign A',
            'fsl_name' => 'A',
            'model_label' => 'A',
            'order' => 1,
        ]);

        $sign2 = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign B',
            'fsl_name' => 'B',
            'model_label' => 'B',
            'order' => 2,
        ]);

        $user1 = User::factory()->create(['name' => 'Finisher Player']);
        PlayerProfile::create(['user_id' => $user1->id, 'total_xp' => 300, 'avatar' => '👑']);

        $user2 = User::factory()->create(['name' => 'Incomplete Player']);
        PlayerProfile::create(['user_id' => $user2->id, 'total_xp' => 150, 'avatar' => '🌱']);

        // User 1 completes both signs
        Progress::create([
            'user_id' => $user1->id,
            'sign_id' => $sign1->id,
            'level_id' => $level->id,
            'is_completed' => true,
        ]);
        Progress::create([
            'user_id' => $user1->id,
            'sign_id' => $sign2->id,
            'level_id' => $level->id,
            'is_completed' => true,
        ]);

        // User 2 completes only 1 sign
        Progress::create([
            'user_id' => $user2->id,
            'sign_id' => $sign1->id,
            'level_id' => $level->id,
            'is_completed' => true,
        ]);

        $response = $this->getJson("/api/finishers?level_id={$level->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $user1->id)
            ->assertJsonPath('data.0.name', 'Finisher Player')
            ->assertJsonPath('data.0.xp', 300)
            ->assertJsonPath('data.0.info', 'Sign Finisher');
    }

    public function test_finishers_are_sorted_by_xp_descending(): void
    {
        $level = Level::create([
            'name' => 'Greetings',
            'description' => 'Greetings level',
            'difficulty' => 'easy',
            'order' => 1,
        ]);

        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Hello',
            'fsl_name' => 'Hello',
            'model_label' => 'Hello',
            'order' => 1,
        ]);

        $lowXpUser = User::factory()->create(['name' => 'Low XP Player']);
        PlayerProfile::create(['user_id' => $lowXpUser->id, 'total_xp' => 100]);

        $highXpUser = User::factory()->create(['name' => 'High XP Player']);
        PlayerProfile::create(['user_id' => $highXpUser->id, 'total_xp' => 900]);

        Progress::create([
            'user_id' => $lowXpUser->id,
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'is_completed' => true,
        ]);
        Progress::create([
            'user_id' => $highXpUser->id,
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'is_completed' => true,
        ]);

        $response = $this->getJson("/api/finishers?level_id={$level->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user_id', $highXpUser->id)
            ->assertJsonPath('data.1.user_id', $lowXpUser->id);
    }

    public function test_global_finishers_endpoint_ranks_by_categories_completed(): void
    {
        $level1 = Level::create(['name' => 'Level 1', 'difficulty' => 'easy', 'order' => 1]);
        $sign1 = Sign::create(['level_id' => $level1->id, 'name' => 'S1', 'fsl_name' => 'S1', 'model_label' => 'S1', 'order' => 1]);

        $level2 = Level::create(['name' => 'Level 2', 'difficulty' => 'medium', 'order' => 2]);
        $sign2 = Sign::create(['level_id' => $level2->id, 'name' => 'S2', 'fsl_name' => 'S2', 'model_label' => 'S2', 'order' => 1]);

        $champion = User::factory()->create(['name' => 'Champion']);
        PlayerProfile::create(['user_id' => $champion->id, 'total_xp' => 500]);

        $beginner = User::factory()->create(['name' => 'Beginner']);
        PlayerProfile::create(['user_id' => $beginner->id, 'total_xp' => 800]); // higher XP but fewer completed categories

        // Champion completes both Level 1 and Level 2
        Progress::create(['user_id' => $champion->id, 'sign_id' => $sign1->id, 'level_id' => $level1->id, 'is_completed' => true]);
        Progress::create(['user_id' => $champion->id, 'sign_id' => $sign2->id, 'level_id' => $level2->id, 'is_completed' => true]);

        // Beginner only completes Level 1
        Progress::create(['user_id' => $beginner->id, 'sign_id' => $sign1->id, 'level_id' => $level1->id, 'is_completed' => true]);

        $response = $this->getJson('/api/finishers');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user_id', $champion->id)
            ->assertJsonPath('data.0.completed_categories_count', 2)
            ->assertJsonPath('data.1.user_id', $beginner->id)
            ->assertJsonPath('data.1.completed_categories_count', 1);
    }

    public function test_empty_finishers_returns_empty_array(): void
    {
        $response = $this->getJson('/api/finishers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }
}
