<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HeartTransaction;
use App\Services\CurriculumEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_fetch_and_manage_notifications()
    {
        $user = User::factory()->create();

        // 1. Record a general curriculum event
        CurriculumEventService::record('lesson-restored', 1, 'Letter A', 'Audio re-synced');

        // 2. Record a personal heart grant
        HeartTransaction::create([
            'user_id' => $user->id,
            'type' => 'admin_grant',
            'amount' => 5,
            'reason' => 'Community reward',
        ]);

        // 3. Fetch notifications
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/notifications');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'unread_count',
            'all_read_at',
            'cleared_at',
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Check distinction between personal and curriculum
        $personal = array_filter($data, fn ($n) => $n['category'] === 'personal');
        $curriculum = array_filter($data, fn ($n) => $n['category'] === 'curriculum');

        $this->assertNotEmpty($personal);
        $this->assertNotEmpty($curriculum);

        $firstId = $data[0]['id'];

        // 4. Mark one as read
        $readRes = $this->actingAs($user, 'sanctum')->postJson('/api/user/notifications/read', [
            'id' => $firstId,
        ]);
        $readRes->assertStatus(200);

        // 5. Mark all as read
        $readAllRes = $this->actingAs($user, 'sanctum')->postJson('/api/user/notifications/read-all');
        $readAllRes->assertStatus(200);

        // Check that unread_count is now 0
        $check = $this->actingAs($user, 'sanctum')->getJson('/api/user/notifications');
        $this->assertEquals(0, $check->json('unread_count'));

        // 6. Delete / dismiss one notification
        $delRes = $this->actingAs($user, 'sanctum')->deleteJson("/api/user/notifications/{$firstId}");
        $delRes->assertStatus(200);

        $checkAfterDel = $this->actingAs($user, 'sanctum')->getJson('/api/user/notifications');
        $dataAfterDel = $checkAfterDel->json('data');
        $idsAfterDel = array_column($dataAfterDel, 'id');
        $this->assertNotContains($firstId, $idsAfterDel);

        // 7. Clear all notifications
        $clearRes = $this->actingAs($user, 'sanctum')->postJson('/api/user/notifications/clear-all');
        $clearRes->assertStatus(200);

        $checkEmpty = $this->actingAs($user, 'sanctum')->getJson('/api/user/notifications');
        $this->assertEmpty($checkEmpty->json('data'));
        $this->assertEquals(0, $checkEmpty->json('unread_count'));
    }
}
