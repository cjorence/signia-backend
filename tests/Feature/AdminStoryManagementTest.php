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

    public function test_admin_cannot_delete_chapter_1(): void
    {
        $chapter1 = Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson('/api/admin/stories/' . $chapter1->id);

        $response->assertStatus(422);
        $this->assertDatabaseHas('stories', ['id' => $chapter1->id]);
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
        $this->assertDatabaseMissing('stories', ['id' => $chapter7->id]);
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

        // Cleanup test file
        if (file_exists(storage_path('app/stories/' . $story->file_name))) {
            unlink(storage_path('app/stories/' . $story->file_name));
        }
    }
}
