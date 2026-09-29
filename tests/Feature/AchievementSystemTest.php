<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Level;
use App\Models\Progress;
use App\Models\Sign;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchievementSeeder::class);
    }

    public function test_user_achievements_endpoint_automatically_evaluates_and_unlocks_eligible_badges(): void
    {
        $user = User::factory()->create();
        $level = Level::create([
            'name' => 'Basics',
            'order' => 1,
            'required_xp' => 0,
        ]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'A',
            'order' => 1,
            'xp_reward' => 20,
        ]);

        // Complete 1 sign
        Progress::create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'sign_id' => $sign->id,
            'is_completed' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/achievements');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // "First Steps" should be unlocked
        $unlockedNames = collect($data)->pluck('achievement.name')->all();
        $this->assertContains('First Steps', $unlockedNames);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
        ]);
    }
}
