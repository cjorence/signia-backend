<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHeartBulkGrantTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createPlayer(string $name, string $email, int $hearts = 3, int $inventory = 0): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'user',
        ]);

        PlayerProfile::create([
            'user_id' => $user->id,
            'current_level' => 1,
            'total_xp' => 0,
            'streak' => 0,
            'hearts' => $hearts,
            'max_hearts' => 5,
            'heart_inventory' => $inventory,
        ]);

        return $user;
    }

    public function test_admin_can_grant_hearts_to_multiple_specific_players(): void
    {
        $admin = $this->createAdmin();
        $player1 = $this->createPlayer('Alice Gamer', 'alice@test.com', 2, 0);
        $player2 = $this->createPlayer('Bob Gamer', 'bob@test.com', 1, 0);
        $player3 = $this->createPlayer('Charlie Gamer', 'charlie@test.com', 4, 0);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/hearts/grant-bulk', [
            'user_ids' => [$player1->id, $player2->id],
            'amount' => 5,
            'reason' => 'Tournament reward',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 2);

        // Player 1 had 2 hearts, got 5: max is 5, usable becomes 5, inventory becomes 2
        $player1Profile = $player1->fresh()->playerProfile;
        $this->assertEquals(5, $player1Profile->hearts);
        $this->assertEquals(2, $player1Profile->heart_inventory);

        // Player 2 had 1 heart, got 5: max is 5, usable becomes 5, inventory becomes 1
        $player2Profile = $player2->fresh()->playerProfile;
        $this->assertEquals(5, $player2Profile->hearts);
        $this->assertEquals(1, $player2Profile->heart_inventory);

        // Player 3 was not targeted and remains unchanged
        $player3Profile = $player3->fresh()->playerProfile;
        $this->assertEquals(4, $player3Profile->hearts);
        $this->assertEquals(0, $player3Profile->heart_inventory);

        $this->assertDatabaseHas('heart_transactions', [
            'user_id' => $player1->id,
            'type' => 'admin_grant',
            'amount' => 5,
            'reason' => 'Tournament reward',
        ]);
    }

    public function test_admin_can_grant_hearts_to_all_players(): void
    {
        $admin = $this->createAdmin();
        $player1 = $this->createPlayer('Player One', 'p1@test.com', 1, 0);
        $player2 = $this->createPlayer('Player Two', 'p2@test.com', 2, 0);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/hearts/grant-bulk', [
            'grant_all' => true,
            'amount' => 3,
            'reason' => 'Server glitch apology',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 2);

        $this->assertEquals(4, $player1->fresh()->playerProfile->hearts);
        $this->assertEquals(5, $player2->fresh()->playerProfile->hearts);
    }

    public function test_non_admin_cannot_grant_bulk_hearts(): void
    {
        $player = $this->createPlayer('Regular Player', 'regular@test.com', 3, 0);
        Sanctum::actingAs($player);

        $response = $this->postJson('/api/admin/hearts/grant-bulk', [
            'grant_all' => true,
            'amount' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_bulk_grant_requires_users_or_grant_all(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/hearts/grant-bulk', [
            'amount' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);
    }
}
