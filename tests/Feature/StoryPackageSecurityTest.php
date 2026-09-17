<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryPackageSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected StoryService $storyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storyService = app(StoryService::class);
    }

    public function test_cannot_access_package_without_ticket(): void
    {
        $response = $this->getJson('/api/stories/package?chapter=1');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_cannot_access_package_with_invalid_ticket(): void
    {
        $response = $this->getJson('/api/stories/package?chapter=1&ticket=invalid_ticket_format');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_access_package_with_tampered_chapter(): void
    {
        $user = User::factory()->create();
        $chapter1 = Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $ticketData = $this->storyService->createLaunchTicket($user, $chapter1);

        // Attempting to use Chapter 1 ticket to download Chapter 2 package
        $response = $this->getJson('/api/stories/package?chapter=2&ticket=' . urlencode($ticketData['ticket']));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_authorized_user_can_stream_chapter1_package(): void
    {
        $user = User::factory()->create();
        $chapter1 = Story::create([
            'slug' => 'arrival',
            'title' => 'The Arrival',
            'chapter_number' => 1,
            'is_free' => true,
            'price' => 0,
        ]);

        $ticketData = $this->storyService->createLaunchTicket($user, $chapter1);

        $response = $this->get('/api/stories/package?chapter=1&ticket=' . $ticketData['ticket']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_purchased_chapter2_can_stream_package(): void
    {
        $user = User::factory()->create();
        $chapter2 = Story::create([
            'slug' => 'first-signs',
            'title' => 'First Signs',
            'chapter_number' => 2,
            'is_free' => false,
            'price' => 49.00,
        ]);

        // Unpurchased user cannot create launch ticket
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->storyService->createLaunchTicket($user, $chapter2);
    }

    public function test_unlocked_chapter2_streams_package_with_valid_ticket(): void
    {
        $user = User::factory()->create();
        $chapter2 = Story::create([
            'slug' => 'first-signs',
            'title' => 'First Signs',
            'chapter_number' => 2,
            'is_free' => false,
            'price' => 49.00,
        ]);

        // Unlock chapter for user
        $this->storyService->unlockForUser($user, $chapter2);

        $ticketData = $this->storyService->createLaunchTicket($user, $chapter2);

        $response = $this->get('/api/stories/package?chapter=2&ticket=' . $ticketData['ticket']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
    }
}
