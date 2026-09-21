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

    public function test_admin_can_upload_and_delete_sign_media_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $level = Level::create([
            'name' => 'FSL Greetings',
            'order' => 1,
            'required_xp' => 0,
        ]);

        Sanctum::actingAs($admin);

        $image = \Illuminate\Http\UploadedFile::fake()->create('hello.jpg', 100, 'image/jpeg');
        $video = \Illuminate\Http\UploadedFile::fake()->create('hello.mp4', 500, 'video/mp4');

        $createRes = $this->postJson('/api/admin/signs', [
            'level_id' => $level->id,
            'name' => 'Hello',
            'model_label' => 'hello',
            'difficulty' => 'easy',
            'xp_reward' => 15,
            'image' => $image,
            'video' => $video,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true);

        $sign = Sign::first();
        $this->assertNotNull($sign->image_url);
        $this->assertNotNull($sign->video_url);

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($sign->image_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($sign->video_url);

        $createRes->assertJsonPath('data.image_url', asset('storage/' . $sign->image_url));
        $createRes->assertJsonPath('data.video_url', asset('storage/' . $sign->video_url));

        // Delete sign
        $this->deleteJson("/api/admin/signs/{$sign->id}")->assertOk();

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($sign->image_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($sign->video_url);
    }

    public function test_admin_can_reorder_signs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $level = Level::create([
            'name' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $sign1 = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign 1',
            'model_label' => 's1',
            'difficulty' => 'easy',
            'xp_reward' => 10,
            'sort_order' => 1,
        ]);

        $sign2 = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign 2',
            'model_label' => 's2',
            'difficulty' => 'easy',
            'xp_reward' => 10,
            'sort_order' => 2,
        ]);

        $res = $this->postJson('/api/admin/signs/reorder', [
            'items' => [
                ['id' => $sign1->id, 'sort_order' => 2],
                ['id' => $sign2->id, 'sort_order' => 1],
            ]
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('signs', ['id' => $sign1->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('signs', ['id' => $sign2->id, 'sort_order' => 1]);
    }

    public function test_admin_can_reorder_levels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $level1 = Level::create(['name' => 'L1', 'order' => 1, 'required_xp' => 0]);
        $level2 = Level::create(['name' => 'L2', 'order' => 2, 'required_xp' => 0]);

        $res = $this->postJson('/api/admin/levels/reorder', [
            'items' => [
                ['id' => $level1->id, 'order' => 2],
                ['id' => $level2->id, 'order' => 1],
            ]
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('levels', ['id' => $level1->id, 'order' => 2]);
        $this->assertDatabaseHas('levels', ['id' => $level2->id, 'order' => 1]);
    }

    public function test_admin_can_archive_and_restore_lesson_sign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Letter A',
            'fsl_name' => 'A',
            'difficulty' => 'easy',
            'xp_reward' => 10,
            'sort_order' => 1,
        ]);

        // 1. Archive the sign
        $archiveRes = $this->postJson("/api/admin/signs/{$sign->id}/archive");
        $archiveRes->assertOk()->assertJsonPath('success', true);

        // Sign should be soft deleted
        $this->assertSoftDeleted('signs', ['id' => $sign->id]);

        // Active list must NOT include the sign
        $activeRes = $this->getJson('/api/signs');
        $activeRes->assertOk()->assertJsonCount(0, 'data');

        // Archived list MUST include the sign
        $archivedRes = $this->getJson('/api/admin/signs/archived');
        $archivedRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Letter A')
            ->assertJsonPath('data.0.is_archived', true);

        // 2. Restore the sign
        $restoreRes = $this->postJson("/api/admin/signs/{$sign->id}/restore");
        $restoreRes->assertOk()->assertJsonPath('success', true);

        // Sign should not be soft deleted anymore
        $this->assertNotSoftDeleted('signs', ['id' => $sign->id]);

        // Active list should have the sign back
        $activeAgainRes = $this->getJson('/api/signs');
        $activeAgainRes->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_student_progress_remains_intact_when_lesson_is_archived(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'user']);

        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Letter B',
            'difficulty' => 'easy',
            'xp_reward' => 10,
            'sort_order' => 1,
        ]);

        // Player completed this sign
        $progress = \App\Models\Progress::create([
            'user_id' => $player->id,
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'is_completed' => true,
            'attempts' => 3,
            'best_confidence' => 0.95,
        ]);

        // Admin archives the sign
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/signs/{$sign->id}/archive")->assertOk();

        // Check progress relationship: sign must NOT be null even when archived
        $loadedProgress = \App\Models\Progress::with('sign')->find($progress->id);
        $this->assertNotNull($loadedProgress->sign);
        $this->assertEquals('Letter B', $loadedProgress->sign->name);
    }

    public function test_admin_can_archive_and_restore_category_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $level = Level::create(['name' => 'FSL Numbers', 'order' => 1, 'required_xp' => 0]);

        // 1. Archive category
        $archiveRes = $this->postJson("/api/admin/levels/{$level->id}/archive");
        $archiveRes->assertOk()->assertJsonPath('success', true);

        // Should be soft-deleted
        $this->assertSoftDeleted('levels', ['id' => $level->id]);

        // Active levels endpoint must not show it
        $activeRes = $this->getJson('/api/levels');
        $activeRes->assertOk()->assertJsonCount(0, 'data');

        // Archived levels endpoint must show it
        $archivedRes = $this->getJson('/api/admin/levels/archived');
        $archivedRes->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'FSL Numbers')
            ->assertJsonPath('data.0.is_archived', true);

        // 2. Restore category
        $restoreRes = $this->postJson("/api/admin/levels/{$level->id}/restore");
        $restoreRes->assertOk()->assertJsonPath('success', true);

        $this->assertNotSoftDeleted('levels', ['id' => $level->id]);

        // Active list should show it again
        $activeResAgain = $this->getJson('/api/levels');
        $activeResAgain->assertOk()->assertJsonCount(1, 'data');
    }
}
