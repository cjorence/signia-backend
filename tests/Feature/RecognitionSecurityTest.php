<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecognitionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function frame(): array
    {
        return ['mode' => 'alphabet', 'image' => 'data:image/jpeg;base64,dGVzdA==', 'stream_id' => 'same-browser-id'];
    }

    public function test_recognition_requires_active_user_and_training_requires_admin(): void
    {
        Http::fake();
        $this->postJson('/api/recognition/predict', $this->frame())->assertUnauthorized();
        $user = User::factory()->create(['is_active' => true, 'role' => 'user']);
        $this->actingAs($user)->getJson('/api/admin/recognition/models')->assertForbidden();
        $this->postJson('/api/admin/recognition/api/train/start', ['category' => 'GREETING'])->assertForbidden();
        $user->update(['is_active' => false]);
        $this->postJson('/api/recognition/predict', $this->frame())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_gateway_overwrites_camera_source_and_isolates_each_account(): void
    {
        config(['recognition.url' => 'http://recognition.internal', 'recognition.token' => str_repeat('s', 40)]);
        $streams = [];
        Http::fake(function ($request) use (&$streams) {
            $this->assertSame('http://recognition.internal/predict', $request->url());
            $this->assertSame('client', $request['frame_source']);
            $this->assertSame('Bearer '.str_repeat('s', 40), $request->header('Authorization')[0]);
            $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $request->header('X-Request-ID')[0]);
            $streams[] = $request['stream_id'];
            return Http::response(['prediction' => 'A', 'confidence' => 0.99]);
        });
        foreach (range(1, 2) as $_) {
            $user = User::factory()->create(['is_active' => true]);
            $this->actingAs($user)->postJson('/api/recognition/predict', $this->frame() + ['frame_source' => 'server'])
                ->assertOk()->assertJsonPath('prediction', 'A');
        }
        $this->assertNotSame($streams[0], $streams[1]);
    }

    public function test_invalid_images_are_rejected_before_contacting_python(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $this->postJson('/api/recognition/predict', array_replace($this->frame(), ['image' => '<script>']))->assertUnprocessable();
        $this->postJson('/api/recognition/predict', array_replace($this->frame(), ['image' => str_repeat('x', 2000001)]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_admin_gateway_has_an_explicit_endpoint_allowlist(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'])]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->getJson('/api/admin/recognition/debug_feed')->assertNotFound();
        $this->getJson('/api/admin/recognition/model_file/.env')->assertNotFound();
        Http::assertNothingSent();
        $this->getJson('/api/admin/recognition/models')->assertOk();
        $this->postJson('/api/admin/recognition/api/train/start', ['category' => 'GREETING'])->assertOk();
        Http::assertSentCount(2);
        $this->postJson('/api/admin/recognition/api/train/start', ['category' => 'GREETING'])->assertOk();
        $this->postJson('/api/admin/recognition/api/train/start', ['category' => 'GREETING'])->assertStatus(429);
        $this->getJson('/api/admin/recognition/models')->assertOk();
    }

    public function test_production_cannot_use_missing_service_credentials_or_test_turnstile_keys(): void
    {
        Http::fake();
        $this->app->instance('env', 'production');
        config(['app.debug' => false, 'recognition.token' => '', 'services.turnstile.secret_key' => '1x0000000000000000000000000000000AA']);
        $this->postJson('/api/auth/send-code', ['email' => 'test@example.com'])->assertStatus(503);
        $this->postJson('/api/auth/forgot-password', ['email' => 'test@example.com'])->assertStatus(503);
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $this->postJson('/api/recognition/predict', $this->frame())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_upstream_internal_errors_are_not_returned_to_users(): void
    {
        Http::fake(['*' => Http::response(['message' => 'internal-sensitive-detail'], 500)]);
        $this->actingAs(User::factory()->create(['is_active' => true]));
        $this->postJson('/api/recognition/predict', $this->frame())->assertStatus(503)->assertDontSee('internal-sensitive-detail');
        $this->mock(StoryService::class)->shouldReceive('resolveAuthorizedPackage')->andThrow(new \RuntimeException('private-file-path'));
        $this->getJson('/api/stories/package?chapter=1&ticket=sample')->assertForbidden()->assertDontSee('private-file-path');
    }
}
