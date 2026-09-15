<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\User;
use App\Services\HeartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeartInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function player(int $hearts, int $inventory): User
    {
        $user = User::factory()->create(['role' => 'user']);

        PlayerProfile::create([
            'user_id' => $user->id,
            'current_level' => 1,
            'total_xp' => 0,
            'hearts' => $hearts,
            'max_hearts' => 5,
            'heart_inventory' => $inventory,
        ]);

        return $user;
    }

    public function test_inventory_credit_is_stored_separately_from_usable_hearts(): void
    {
        $user = $this->player(2, 0);

        $profile = app(HeartService::class)->creditInventory($user, 15, 'test_purchase');

        $this->assertSame(2, $profile->hearts);
        $this->assertSame(15, $profile->heart_inventory);
        $this->assertDatabaseHas('heart_transactions', [
            'user_id' => $user->id,
            'type' => 'inventory_credit',
            'amount' => 15,
            'reason' => 'test_purchase',
        ]);
    }

    public function test_inventory_refill_moves_credits_to_usable_hearts_without_exceeding_cap(): void
    {
        $user = $this->player(2, 4);

        $profile = app(HeartService::class)->refillFromInventory($user);

        $this->assertSame(5, $profile->hearts);
        $this->assertSame(1, $profile->heart_inventory);
        $this->assertDatabaseHas('heart_transactions', [
            'user_id' => $user->id,
            'type' => 'inventory_refill',
            'amount' => -3,
            'reason' => 'inventory_to_usable_hearts',
        ]);
    }

    public function test_refill_rejects_a_requested_amount_above_available_inventory(): void
    {
        $user = $this->player(1, 2);

        try {
            app(HeartService::class)->refillFromInventory($user, 3);
            $this->fail('Expected a validation exception for insufficient heart inventory.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('heart_inventory', $exception->errors());
        }

        $this->assertSame(1, $user->playerProfile->fresh()->hearts);
        $this->assertSame(2, $user->playerProfile->fresh()->heart_inventory);
    }

    public function test_refill_preserves_inventory_when_usable_hearts_are_already_full(): void
    {
        $user = $this->player(5, 10);

        try {
            app(HeartService::class)->refillFromInventory($user);
            $this->fail('Expected a validation exception for full usable hearts.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('hearts', $exception->errors());
        }

        $this->assertSame(5, $user->playerProfile->fresh()->hearts);
        $this->assertSame(10, $user->playerProfile->fresh()->heart_inventory);
    }

    public function test_authenticated_player_can_refill_usable_hearts_from_inventory(): void
    {
        $user = $this->player(3, 5);
        Sanctum::actingAs($user);

        $this->postJson('/api/user/hearts/refill')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hearts', 5)
            ->assertJsonPath('data.heart_inventory', 3);

        $this->assertDatabaseHas('heart_transactions', [
            'user_id' => $user->id,
            'type' => 'inventory_refill',
            'amount' => -2,
        ]);
    }

    public function test_regeneration_does_not_create_zero_change_audit_entries(): void
    {
        $user = $this->player(4, 0);
        PlayerProfile::where('user_id', $user->id)->update([
            'next_heart_at' => now()->subMinutes(31),
        ]);

        $this->assertNotNull($user->playerProfile()->first()?->next_heart_at);
        $this->assertTrue($user->playerProfile()->first()->next_heart_at->isPast());

        $hearts = app(HeartService::class);
        $hearts->regenerate($user);
        $hearts->regenerate($user);

        $this->assertSame(1, $user->heartTransactions()
            ->where('type', 'regen')
            ->count());
        $this->assertDatabaseMissing('heart_transactions', [
            'user_id' => $user->id,
            'type' => 'regen',
            'amount' => 0,
        ]);
    }
}
