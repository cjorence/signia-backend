<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PreferenceController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function device(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test-device')->plainTextToken];
    }

    public function test_preferences_follow_account_across_devices_and_ip_changes(): void
    {
        $user = $this->user();
        $first = $this->device($user);
        $second = $this->device($user);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->getJson('/api/auth/preferences', $first)
            ->assertOk()->assertJsonPath('data.preferences', PreferenceController::DEFAULTS);
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['dominantHand' => 'left', 'soundEffects' => false]], $first)
            ->assertOk()->assertJsonPath('data.revision', 1);
        app('auth')->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])->getJson('/api/auth/preferences', $second)
            ->assertOk()->assertJsonPath('data.preferences.dominantHand', 'left')->assertJsonPath('data.preferences.soundEffects', false);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'preferences_revision' => 1]);
    }

    public function test_other_accounts_cannot_read_or_overwrite_preferences(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['highContrast' => true]], $this->device($a))->assertOk();
        $headers = $this->device($b);
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/preferences?user_id='.$a->id, $headers)->assertOk()->assertJsonPath('data.preferences.highContrast', false);
        $this->patchJson('/api/auth/preferences', ['user_id' => $a->id, 'revision' => 1, 'preferences' => ['highContrast' => false]], $headers)->assertUnprocessable();
        $this->assertTrue($a->fresh()->preferences['highContrast']);
    }

    public function test_stale_device_cannot_silently_overwrite_newer_settings(): void
    {
        $user = $this->user();
        $first = $this->device($user);
        $second = $this->device($user);
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['cameraMirror' => false]], $first)->assertOk();
        app('auth')->forgetGuards();
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['dominantHand' => 'left']], $second)
            ->assertStatus(409)->assertJsonPath('data.preferences.cameraMirror', false)->assertJsonPath('data.revision', 1);
        $this->patchJson('/api/auth/preferences', ['revision' => 1, 'preferences' => ['dominantHand' => 'left']], $second)
            ->assertOk()->assertJsonPath('data.preferences.cameraMirror', false)->assertJsonPath('data.preferences.dominantHand', 'left');
    }

    public function test_invalid_fields_do_not_modify_account_or_preferences(): void
    {
        $user = $this->user();
        $headers = $this->device($user);
        foreach ([['dominantHand' => 'other'], ['cameraMirror' => 'false'], ['role' => 'admin'], ['highContrast' => null]] as $changes) {
            $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => $changes], $headers)->assertUnprocessable();
        }
        $this->assertSame(0, $user->fresh()->preferences_revision);
        $this->assertNull($user->fresh()->preferences);
    }

    public function test_logout_revokes_only_that_device_and_preserves_saved_data(): void
    {
        $user = $this->user();
        $first = $this->device($user);
        $second = $this->device($user);
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['dailyReminder' => false]], $first)->assertOk();
        $this->postJson('/api/auth/logout', [], $first)->assertOk();
        // Clear cached request guards to model independent HTTP requests.
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/preferences', $first)->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/preferences', $second)->assertOk()->assertJsonPath('data.preferences.dailyReminder', false);
    }

    public function test_expired_revoked_and_inactive_sessions_cannot_change_preferences(): void
    {
        $user = $this->user();
        $token = $user->createToken('old-device');
        $token->accessToken->forceFill(['created_at' => now()->subDays(31)])->save();
        $this->getJson('/api/auth/preferences', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
        $user->is_active = false;
        $user->save();
        app('auth')->forgetGuards();
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['highContrast' => true]], $this->device($user))->assertForbidden();
        $this->assertNull($user->fresh()->preferences);
    }

    public function test_anonymous_clients_cannot_access_preferences(): void
    {
        $this->getJson('/api/auth/preferences')->assertUnauthorized();
        $this->patchJson('/api/auth/preferences', ['revision' => 0, 'preferences' => ['highContrast' => true]])->assertUnauthorized();
    }
}
