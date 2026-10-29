<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Models\UserStoryProgress;
use App\Models\UserStoryUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryProgressTest extends TestCase
{
    use RefreshDatabase;

    private function story(int $chapter = 1): Story
    {
        return Story::create(['slug' => 'chapter-'.$chapter, 'title' => 'Chapter '.$chapter,
            'chapter_number' => $chapter, 'is_free' => $chapter === 1, 'episodes' => 3]);
    }

    private function progress(int $step = 2, int $total = 10): array
    {
        return ['current_step' => $step, 'total_steps' => $total,
            'save_state' => ['waypoint_index' => $step], 'status' => 'in_progress'];
    }

    public function test_progress_requires_authentication_and_story_ownership(): void
    {
        $story = $this->story(2);
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress())->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress())
            ->assertUnprocessable()->assertJsonValidationErrors('story');
        $this->postJson("/api/user/stories/{$story->id}/complete")
            ->assertUnprocessable()->assertJsonValidationErrors('story');
        $this->assertDatabaseCount('user_story_progress', 0);
    }

    public function test_unlocked_story_progress_round_trips_without_leaking_between_users(): void
    {
        $story = $this->story(2);
        $player = User::factory()->create();
        UserStoryUnlock::create(['user_id' => $player->id, 'story_id' => $story->id]);
        Sanctum::actingAs($player);
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress())
            ->assertOk()->assertJsonPath('data.progress_percent', 20);
        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertOk()->assertJsonPath('data.save_state.waypoint_index', 2);
        $this->getJson('/api/stories')->assertOk()->assertJsonPath('data.0.progress.percent', 20);
        $other = User::factory()->create();
        UserStoryUnlock::create(['user_id' => $other->id, 'story_id' => $story->id]);
        Sanctum::actingAs($other);
        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertOk()->assertJsonPath('data.save_state', null);
    }

    public function test_late_progress_cannot_undo_completion_or_change_its_timestamp(): void
    {
        $story = $this->story();
        $player = User::factory()->create();
        $player->playerProfile()->create(['current_level' => 1]);
        Sanctum::actingAs($player);
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress())->assertOk();
        $this->postJson("/api/user/stories/{$story->id}/complete")->assertOk()->assertJsonPath('data.stories_done', 1);
        $completedAt = UserStoryProgress::firstOrFail()->completed_at->toISOString();
        $this->travel(1)->minutes();
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress(1))
            ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.progress_percent', 100);
        $this->postJson("/api/user/stories/{$story->id}/complete")->assertOk();
        $this->assertSame($completedAt, UserStoryProgress::firstOrFail()->completed_at->toISOString());
        $this->assertSame(1, $player->storyProgress()->where('status', 'completed')->count());
        $this->getJson('/api/stories')->assertOk()
            ->assertJsonPath('data.0.progress.current_step', 10)->assertJsonPath('data.0.progress.total_steps', 10);
    }

    public function test_near_complete_progress_does_not_round_up_to_completion(): void
    {
        $story = $this->story();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress(999, 1000))
            ->assertOk()->assertJsonPath('data.status', 'in_progress')->assertJsonPath('data.progress_percent', 99);
    }

    public function test_invalid_step_bounds_are_rejected(): void
    {
        $story = $this->story();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress(11, 10))
            ->assertUnprocessable()->assertJsonValidationErrors('current_step');
        $this->postJson("/api/user/stories/{$story->id}/progress", $this->progress(0, 0))
            ->assertUnprocessable()->assertJsonValidationErrors('total_steps');
    }
}
