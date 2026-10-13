<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_can_sign_up_with_google(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-12345',
                'email' => 'newuser@gmail.com',
                'email_verified' => 'true',
                'name' => 'Real Gmail User',
                'picture' => 'https://example.com/avatar.png',
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'credential' => 'valid-mock-jwt-credential',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'newuser@gmail.com',
                        'name' => 'Real Gmail User',
                        'role' => 'user',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@gmail.com',
            'google_id' => 'google-user-12345',
        ]);

        $user = User::where('email', 'newuser@gmail.com')->first();
        $this->assertNotNull($user->playerProfile);
        $this->assertEquals(1, $user->playerProfile->current_level);
    }

    public function test_existing_email_password_user_can_log_in_via_google_and_retain_password(): void
    {
        $existing = User::create([
            'name' => 'Existing User',
            'email' => 'existing@gmail.com',
            'password' => 'Password123!',
            'role' => 'user',
            'is_active' => true,
        ]);
        $existing->playerProfile()->create(['current_level' => 2, 'total_xp' => 150]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-existing-99',
                'email' => 'existing@gmail.com',
                'email_verified' => 'true',
                'name' => 'Existing User',
            ], 200),
        ]);

        // 1. Log in via Google
        $response = $this->postJson('/api/auth/google', [
            'credential' => 'valid-mock-jwt',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Check Google ID is linked
        $existing->refresh();
        $this->assertEquals('google-user-existing-99', $existing->google_id);

        // 2. Both methods work: user can STILL log in via standard email and password!
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'existing@gmail.com',
            'password' => 'Password123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_google_only_user_receives_helpful_message_on_password_login(): void
    {
        User::create([
            'name' => 'Google Only User',
            'email' => 'googleonly@gmail.com',
            'google_id' => 'google-only-id',
            'password' => null,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'googleonly@gmail.com',
            'password' => 'AnyPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This account was created with Google. Please use "Continue with Google" to log in, or set a password in your account profile.',
            ]);
    }

    public function test_google_user_can_set_password_in_profile_without_current_password(): void
    {
        $user = User::create([
            'name' => 'Google User',
            'email' => 'googleuser@gmail.com',
            'google_id' => 'google-id-555',
            'password' => null,
            'is_active' => true,
        ]);
        $user->playerProfile()->create(['current_level' => 1]);

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson('/api/auth/profile', [
            'password' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));

        // Now user can log in with password!
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'googleuser@gmail.com',
            'password' => 'NewSecurePassword123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_invalid_google_credential_is_rejected(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response(['error' => 'invalid_token'], 400),
        ]);

        $response = $this->postJson('/api/auth/google', [
            'credential' => 'bogus-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to verify Google account. Please try again.',
            ]);
    }
}
