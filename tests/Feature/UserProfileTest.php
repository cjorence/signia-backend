<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_name_and_avatar(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'player@signia.test',
            'password' => Hash::make('password123'),
        ]);

        $user->playerProfile()->create([
            'current_level' => 1,
            'total_xp' => 100,
            'streak' => 2,
            'avatar' => '/assets/mascot.png',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/user/profile', [
            'name' => 'Updated Champion',
            'avatar' => '⚡',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Updated Champion',
                        'player_profile' => [
                            'avatar' => '⚡',
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Champion',
        ]);

        $this->assertDatabaseHas('player_profiles', [
            'user_id' => $user->id,
            'avatar' => '⚡',
        ]);
    }

    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldPassword123'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/user/profile', [
            'current_password' => 'oldPassword123',
            'password' => 'newSecretPass456',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newSecretPass456', $user->password));
    }

    public function test_password_change_fails_if_current_password_is_wrong(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctPassword'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/user/profile', [
            'current_password' => 'wrongPassword',
            'password' => 'newSecretPass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }
}
