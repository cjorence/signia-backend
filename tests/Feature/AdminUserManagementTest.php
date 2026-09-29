<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->player = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        PlayerProfile::create([
            'user_id' => $this->player->id,
            'current_level' => 3,
            'total_xp' => 150,
            'streak' => 2,
            'hearts' => 5,
        ]);
    }

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $response = $this->actingAs($this->player)->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                        'player_profile',
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_user_details(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/admin/users/{$this->player->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->player->id);
    }

    public function test_admin_can_deactivate_and_activate_user(): void
    {
        // Deactivate
        $deactivateRes = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->player->id}/deactivate");
        $deactivateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($this->player->fresh()->is_active);

        // Activate
        $activateRes = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->player->id}/activate");
        $activateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($this->player->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->admin)->patchJson("/api/admin/users/{$this->admin->id}/deactivate");
        $response->assertStatus(400);

        $this->assertTrue($this->admin->fresh()->is_active);
    }
}
