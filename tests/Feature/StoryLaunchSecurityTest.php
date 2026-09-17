<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Models\UserStoryUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryLaunchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_request_launch_ticket(): void
    {
        $story = Story::create([
            'slug' => 'arrival',
            'title' => 'Bagong Mukha',
            'chapter_number' => 1,
            'is_free' => true,
        ]);

        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertUnauthorized();
    }

    public function test_player_can_get_launch_ticket_for_free_chapter(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $story = Story::create([
            'slug' => 'arrival',
            'title' => 'Bagong Mukha',
            'chapter_number' => 1,
            'is_free' => true,
        ]);

        Sanctum::actingAs($player);

        $response = $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['ticket', 'chapter_number', 'story_id', 'expires_at']]);

        $ticket = $response->json('data.ticket');

        // Verify ticket using the public verification endpoint
        $this->postJson('/api/stories/verify-ticket', [
            'ticket' => $ticket,
            'chapter' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.chapter_number', 1);
    }

    public function test_player_cannot_get_launch_ticket_for_locked_chapter(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $story = Story::create([
            'slug' => 'plaza',
            'title' => 'Unang Gabi sa Plaza',
            'chapter_number' => 2,
            'is_free' => false,
            'price' => 49.00,
        ]);

        Sanctum::actingAs($player);

        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['story']);
    }

    public function test_tampered_chapter_fails_ticket_verification(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $story = Story::create([
            'slug' => 'arrival',
            'title' => 'Bagong Mukha',
            'chapter_number' => 1,
            'is_free' => true,
        ]);

        Sanctum::actingAs($player);

        $ticket = $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->json('data.ticket');

        // Attacker attempts to use Chapter 1 ticket to access Chapter 2
        $this->postJson('/api/stories/verify-ticket', [
            'ticket' => $ticket,
            'chapter' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_forged_ticket_fails_signature_verification(): void
    {
        $forgedTicket = base64_encode(json_encode([
            'user_id' => 1,
            'chapter_number' => 2,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ])).'.fake_signature';

        $this->postJson('/api/stories/verify-ticket', [
            'ticket' => $forgedTicket,
            'chapter' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_unlocked_story_grants_ticket(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $story = Story::create([
            'slug' => 'plaza',
            'title' => 'Unang Gabi sa Plaza',
            'chapter_number' => 2,
            'is_free' => false,
            'price' => 49.00,
        ]);

        UserStoryUnlock::create([
            'user_id' => $player->id,
            'story_id' => $story->id,
            'unlocked_at' => now(),
        ]);

        Sanctum::actingAs($player);

        $ticket = $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertOk()
            ->json('data.ticket');

        $this->postJson('/api/stories/verify-ticket', [
            'ticket' => $ticket,
            'chapter' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.chapter_number', 2);
    }
}
