<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Progress;
use App\Models\Sign;
use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_follow_story_content_and_progress_is_personal(): void
    {
        $alphabet = Level::create(['name' => 'FSL Alphabet', 'order' => 1]);
        $greetings = Level::create(['name' => 'FSL Greetings', 'order' => 2]);
        $survival = Level::create(['name' => 'FSL Survival', 'order' => 3]);
        $a = Sign::create(['level_id' => $alphabet->id, 'name' => 'Letter A']);
        Sign::create(['level_id' => $alphabet->id, 'name' => 'Letter B']);
        $archived = Sign::create(['level_id' => $alphabet->id, 'name' => 'Old lesson']);
        $archived->delete();
        $user = User::factory()->create();
        $other = User::factory()->create();
        Progress::create(['user_id' => $user->id, 'level_id' => $alphabet->id, 'sign_id' => $a->id, 'is_completed' => true]);
        Story::create(['slug' => 'arrival', 'title' => 'Bagong Mukha', 'chapter_number' => 1]);
        Story::create(['slug' => 'plaza', 'title' => 'Unang Gabi sa Plaza', 'chapter_number' => 2, 'price' => 49]);

        $stories = app(StoryService::class)->getStoriesForUser($user);
        $this->assertSame(1, $stories[0]['recommended_lessons'][0]['completed_lessons']);
        $this->assertSame(2, $stories[0]['recommended_lessons'][0]['total_lessons']);
        $this->assertFalse($stories[0]['recommended_lessons'][0]['is_completed']);
        $this->assertSame([$greetings->id, $survival->id], array_column($stories[1]['recommended_lessons'], 'id'));
        $this->assertTrue($stories[1]['can_unlock_with_payment']);
        $this->assertSame(0, app(StoryService::class)->getStoriesForUser($other)[0]['recommended_lessons'][0]['completed_lessons']);
    }

    public function test_admin_can_save_replace_and_clear_recommendations_and_players_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();
        $level = Level::create(['name' => 'FSL Alphabet', 'order' => 1]);
        $other = Level::create(['name' => 'FSL Numbers', 'order' => 2]);
        $story = Story::create(['slug' => 'arrival', 'title' => 'Story', 'chapter_number' => 1]);
        $this->actingAs($admin)->getJson('/api/admin/stories')->assertOk()->assertJsonPath('data.0.recommended_level_ids', [$level->id]);
        $this->actingAs($player)->postJson('/api/admin/stories/'.$story->id, ['recommended_level_ids' => [$other->id]])->assertForbidden();
        // Same JSON string that the multipart editor sends.
        $this->actingAs($admin)->postJson('/api/admin/stories/'.$story->id, ['recommended_level_ids' => json_encode([$other->id])])->assertOk();
        $this->assertSame([$other->id], $story->fresh()->recommended_level_ids);
        $this->assertSame('FSL Numbers', app(StoryService::class)->getStoriesForUser($player)[0]['recommended_lessons'][0]['title']);
        $this->postJson('/api/admin/stories/'.$story->id, ['title' => 'Renamed story'])->assertOk();
        $this->assertSame([$other->id], $story->fresh()->recommended_level_ids);
        $this->postJson('/api/admin/stories/'.$story->id, ['recommended_level_ids' => '[]'])->assertOk();
        $this->assertSame([], app(StoryService::class)->getStoriesForUser($player)[0]['recommended_lessons']);
        $this->postJson('/api/admin/stories', ['title' => 'New story', 'recommended_level_ids' => [$level->id]])->assertCreated()->assertJsonPath('data.recommended_level_ids', [$level->id]);
    }

    public function test_invalid_and_archived_selections_are_rejected_and_archived_groups_are_hidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $level = Level::create(['name' => 'FSL Alphabet', 'order' => 1]);
        $story = Story::create(['slug' => 'arrival', 'title' => 'Story', 'chapter_number' => 1, 'recommended_level_ids' => [$level->id]]);
        $level->delete();
        foreach (['broken json', '{"id":1}', [99999], [$level->id], [[1]]] as $selection) {
            $this->postJson('/api/admin/stories/'.$story->id, ['recommended_level_ids' => $selection])->assertUnprocessable();
        }
        $this->assertSame([], app(StoryService::class)->getStoriesForUser(null)[0]['recommended_lessons']);
        $this->assertSame([$level->id], $story->fresh()->recommended_level_ids);
    }
}
