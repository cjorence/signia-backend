<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_promotion_requires_verified_active_account_and_explicit_apply(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true, 'email_verified_at' => now()]);
        $this->artisan('signia:promote-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertSame('user', $user->fresh()->role);
        $this->artisan('signia:promote-admin', ['email' => $user->email, '--apply' => true])->assertSuccessful();
        $this->assertSame('admin', $user->fresh()->role);
        $user = $user->fresh();
        $user->forceFill(['role' => 'user', 'email_verified_at' => null])->save();
        $this->artisan('signia:promote-admin', ['email' => $user->email, '--apply' => true])->assertFailed();
        $this->assertSame('user', $user->fresh()->role);
        $user->forceFill(['email_verified_at' => now(), 'is_active' => false])->save();
        $this->artisan('signia:promote-admin', ['email' => $user->email, '--apply' => true])->assertFailed();
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_production_demo_seeding_is_rejected_before_any_users_are_created(): void
    {
        $before = User::count();
        $this->app->instance('env', 'production');
        try {
            (new \Database\Seeders\DatabaseSeeder)->run();
            $this->fail('Production seeding must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Demo accounts are prohibited', $e->getMessage());
        }
        $this->assertSame($before, User::count());
    }
}
