<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Sign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLessonSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_level_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // 1. Create Level
        $createRes = $this->postJson('/api/admin/levels', [
            'name' => 'FSL Alphabet',
            'description' => 'Learn the A to Z alphabet.',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'FSL Alphabet');

        $levelId = $createRes->json('data.id');

        // 2. Update Level
        $updateRes = $this->putJson("/api/admin/levels/{$levelId}", [
            'name' => 'FSL Alphabet Updated',
            'description' => 'Updated description.',
            'order' => 2,
            'required_xp' => 50,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'FSL Alphabet Updated')
            ->assertJsonPath('data.required_xp', 50);

        // 3. Delete Level
        $deleteRes = $this->deleteJson("/api/admin/levels/{$levelId}");
        $deleteRes->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('levels', ['id' => $levelId]);
    }

    public function test_admin_can_create_update_and_delete_lesson_sign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $level = Level::create([
            'name' => 'FSL Greetings',
            'description' => 'Common greetings',
            'order' => 1,
            'required_xp' => 0,
        ]);

        Sanctum::actingAs($admin);

        // 1. Create Sign
        $createRes = $this->postJson('/api/admin/signs', [
            'level_id' => $level->id,
            'name' => 'Hello',
            'fsl_name' => 'Kamusta',
            'description' => 'Wave hand with open palm near temple.',
            'model_label' => 'hello',
            'difficulty' => 'easy',
            'xp_reward' => 15,
            'image_url' => 'https://example.com/hello.png',
            'video_url' => 'https://example.com/hello.mp4',
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Hello')
            ->assertJsonPath('data.fsl_name', 'Kamusta')
            ->assertJsonPath('data.model_label', 'hello')
            ->assertJsonPath('data.xp_reward', 15);

        $signId = $createRes->json('data.id');

        // 2. Fetch all signs endpoint
        $allRes = $this->getJson('/api/signs');
        $allRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        // 3. Update Sign
        $updateRes = $this->putJson("/api/admin/signs/{$signId}", [
            'name' => 'Hello (Formal)',
            'fsl_name' => 'Magandang Araw',
            'difficulty' => 'medium',
            'xp_reward' => 20,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Hello (Formal)')
            ->assertJsonPath('data.fsl_name', 'Magandang Araw')
            ->assertJsonPath('data.difficulty', 'medium')
            ->assertJsonPath('data.xp_reward', 20);

        // 4. Delete Sign
        $deleteRes = $this->deleteJson("/api/admin/signs/{$signId}");
        $deleteRes->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('signs', ['id' => $signId]);
    }

    public function test_non_admin_cannot_manage_levels_and_signs(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $level = Level::create([
            'name' => 'Level 1',
            'order' => 1,
            'required_xp' => 0,
        ]);

        Sanctum::actingAs($player);

        $this->postJson('/api/admin/levels', [
            'name' => 'Hacked Level',
            'order' => 1,
            'required_xp' => 0,
        ])->assertForbidden();

        $this->postJson('/api/admin/signs', [
            'level_id' => $level->id,
            'name' => 'Hacked Sign',
            'model_label' => 'hacked',
            'difficulty' => 'easy',
            'xp_reward' => 10,
        ])->assertForbidden();
    }
}
