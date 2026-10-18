<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->player = User::factory()->create([
            'role' => 'user',
        ]);
    }

    public function test_non_admin_cannot_access_admin_stories(): void
    {
        $response = $this->actingAs($this->player)->getJson('/api/admin/stories');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_stories(): void
    {
        Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/stories');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment(['title' => 'The Arrival']);
    }

    public function test_admin_can_create_new_story_chapter(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/stories', [
            'title' => 'The Secret Pass',
            'description' => 'A hidden valley with new FSL signs.',
            'chapter_number' => 7,
            'is_free' => false,
            'price' => 79.00,
            'required_lesson_ids' => ['level_1', 'level_2'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'The Secret Pass',
                    'chapter_number' => 7,
                    'is_free' => false,
                    'price' => '79.00',
                ],
            ]);

        $this->assertDatabaseHas('stories', [
            'title' => 'The Secret Pass',
            'chapter_number' => 7,
            'price' => 79.00,
        ]);
    }

    public function test_admin_cannot_force_delete_chapter_1(): void
    {
        $chapter1 = Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson('/api/admin/stories/' . $chapter1->id . '/force');

        $response->assertStatus(422);
        $this->assertDatabaseHas('stories', ['id' => $chapter1->id]);
    }

    public function test_admin_can_archive_chapter_1(): void
    {
        $chapter1 = Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/stories/' . $chapter1->id . '/archive');

        $response->assertStatus(200);
        $this->assertSoftDeleted('stories', ['id' => $chapter1->id]);
    }

    public function test_admin_can_delete_custom_chapter(): void
    {
        $chapter7 = Story::create([
            'slug' => 'secret-pass',
            'title' => 'The Secret Pass',
            'chapter_number' => 7,
            'is_free' => false,
            'price' => 79.00,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson('/api/admin/stories/' . $chapter7->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('stories', ['id' => $chapter7->id]);
    }

    public function test_admin_can_archive_and_restore_story_chapter(): void
    {
        $chapter = Story::create([
            'slug' => 'hidden-valley',
            'title' => 'The Hidden Valley',
            'chapter_number' => 3,
            'is_free' => false,
            'price' => 59.00,
        ]);

        // Archive
        $archiveRes = $this->actingAs($this->admin)->postJson('/api/admin/stories/' . $chapter->id . '/archive');
        $archiveRes->assertStatus(200);
        $this->assertSoftDeleted('stories', ['id' => $chapter->id]);

        // List archived
        $listRes = $this->actingAs($this->admin)->getJson('/api/admin/stories/archived');
        $listRes->assertStatus(200)->assertJsonFragment(['title' => 'The Hidden Valley']);

        // Restore
        $restoreRes = $this->actingAs($this->admin)->postJson('/api/admin/stories/' . $chapter->id . '/restore');
        $restoreRes->assertStatus(200);
        $this->assertNotSoftDeleted('stories', ['id' => $chapter->id]);
    }

    public function test_admin_can_bulk_archive_and_bulk_restore_stories(): void
    {
        $ch2 = Story::create(['slug' => 'ch2', 'title' => 'Chapter 2', 'chapter_number' => 2]);
        $ch3 = Story::create(['slug' => 'ch3', 'title' => 'Chapter 3', 'chapter_number' => 3]);

        // Bulk archive
        $res = $this->actingAs($this->admin)->postJson('/api/admin/stories/bulk-archive', [
            'ids' => [$ch2->id, $ch3->id],
        ]);
        $res->assertStatus(200)->assertJson(['count' => 2]);
        $this->assertSoftDeleted('stories', ['id' => $ch2->id]);
        $this->assertSoftDeleted('stories', ['id' => $ch3->id]);

        // Bulk restore
        $resRestore = $this->actingAs($this->admin)->postJson('/api/admin/stories/bulk-restore', [
            'ids' => [$ch2->id, $ch3->id],
        ]);
        $resRestore->assertStatus(200)->assertJson(['count' => 2]);
        $this->assertNotSoftDeleted('stories', ['id' => $ch2->id]);
        $this->assertNotSoftDeleted('stories', ['id' => $ch3->id]);
    }

    public function test_admin_cannot_bulk_force_delete_chapter_1(): void
    {
        $ch1 = Story::create(['slug' => 'ch1', 'title' => 'Chapter 1', 'chapter_number' => 1]);
        $ch2 = Story::create(['slug' => 'ch2', 'title' => 'Chapter 2', 'chapter_number' => 2]);

        $res = $this->actingAs($this->admin)->postJson('/api/admin/stories/bulk-force-delete', [
            'ids' => [$ch1->id, $ch2->id],
        ]);
        $res->assertStatus(422);
    }

    public function test_admin_can_force_delete_story_without_records(): void
    {
        $chapter = Story::create([
            'slug' => 'temp-chapter',
            'title' => 'Temp Chapter',
            'chapter_number' => 99,
        ]);

        $chapter->delete(); // archive first
        $this->assertSoftDeleted('stories', ['id' => $chapter->id]);

        $res = $this->actingAs($this->admin)->deleteJson('/api/admin/stories/' . $chapter->id . '/force');
        $res->assertStatus(200);
        $this->assertDatabaseMissing('stories', ['id' => $chapter->id]);
    }

    public function test_admin_cannot_force_delete_story_with_player_unlocks(): void
    {
        $chapter = Story::create([
            'slug' => 'paid-chapter',
            'title' => 'Paid Chapter',
            'chapter_number' => 5,
        ]);

        \App\Models\UserStoryUnlock::create([
            'user_id' => $this->player->id,
            'story_id' => $chapter->id,
            'unlocked_at' => now(),
        ]);

        $res = $this->actingAs($this->admin)->deleteJson('/api/admin/stories/' . $chapter->id . '/force');
        $res->assertStatus(422);
        $this->assertDatabaseHas('stories', ['id' => $chapter->id]);
    }

    public function test_admin_can_upload_pck_package_and_cover(): void
    {
        $cover = UploadedFile::fake()->create('cover.jpg', 500, 'image/jpeg');
        $pck = UploadedFile::fake()->create('chapter_8.pck', 1024, 'application/octet-stream');

        $response = $this->actingAs($this->admin)->post('/api/admin/stories', [
            'title' => 'The Mountain Sanctuary',
            'description' => 'Advanced FSL signs.',
            'chapter_number' => 8,
            'is_free' => false,
            'price' => 99.00,
            'cover_photo' => $cover,
            'package_file' => $pck,
        ]);

        $response->assertStatus(201);
        $story = Story::where('chapter_number', 8)->first();
        $this->assertNotNull($story);
        $this->assertNotNull($story->file_name);
        $this->assertStringStartsWith('chapter_8_', $story->file_name);
        $this->assertFileExists(storage_path('app/stories/' . $story->file_name));

        // Clean up both uploaded files so the suite leaves the checkout unchanged.
        if ($story->cover_url && file_exists(public_path(ltrim($story->cover_url, '/')))) {
            unlink(public_path(ltrim($story->cover_url, '/')));
        }
        // Cleanup test file
        if (file_exists(storage_path('app/stories/' . $story->file_name))) {
            unlink(storage_path('app/stories/' . $story->file_name));
        }
    }
}
