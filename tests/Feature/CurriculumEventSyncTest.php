<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sign;
use App\Models\Level;
use App\Services\CurriculumEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumEventSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_events_are_recorded_and_retrieved(): void
    {
        // 1. Record events
        CurriculumEventService::record('lesson-archived', 101, 'Alphabet A', 'Content refresh');
        CurriculumEventService::record('lesson-restored', 101, 'Alphabet A');

        // 2. Query endpoint
        $response = $this->getJson('/api/curriculum/events');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('lesson-restored', $data[0]['type']);
        $this->assertEquals('Alphabet A', $data[0]['name']);
        $this->assertEquals('lesson-archived', $data[1]['type']);
        $this->assertEquals('Content refresh', $data[1]['reason']);
    }

    public function test_admin_archiving_sign_records_curriculum_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $level = Level::create([
            'name' => 'Basics',
            'order' => 1,
            'difficulty' => 'easy',
        ]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign Hello',
            'difficulty' => 'easy',
            'xp_reward' => 10,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/signs/{$sign->id}/archive", [
            'reason' => 'Camera angle correction',
        ]);

        $response->assertStatus(200);

        $eventsResponse = $this->getJson('/api/curriculum/events');
        $eventsResponse->assertStatus(200);
        $events = $eventsResponse->json('data');

        $this->assertNotEmpty($events);
        $this->assertEquals('lesson-archived', $events[0]['type']);
        $this->assertEquals('Sign Hello', $events[0]['name']);
        $this->assertEquals('Camera angle correction', $events[0]['reason']);
    }
}
