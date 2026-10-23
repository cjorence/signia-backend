<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrainingModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_mode_rejects_mutations_before_validation_or_upstream_work(): void
    {
        config(['recognition.training_enabled' => false]);
        Http::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach (['start', 'upload_video'] as $endpoint) {
            $this->postJson('/api/admin/recognition/api/train/'.$endpoint, [])
                ->assertStatus(409)->assertJsonPath('code', 'training_disabled');
        }
        Http::assertNothingSent();
    }

    public function test_capability_requires_both_services_and_fails_closed_on_old_responses(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $cases = [[true, true, true], [false, true, false], [true, false, false], [true, 'true', false], [true, null, false]];
        $sequence = Http::sequence();
        foreach ($cases as [$local, $remote, $expected]) {
            $sequence->push(['status' => 'success', 'training_enabled' => $remote, 'training_busy' => true]);
        }
        Http::fake(['*' => $sequence]);
        foreach ($cases as [$local, $remote, $expected]) {
            config(['recognition.training_enabled' => $local]);
            $this->getJson('/api/admin/recognition/api/train/capabilities')->assertOk()
                ->assertJsonPath('training_enabled', $expected)->assertJsonPath('training_busy', true);
        }
    }

    public function test_python_disabled_response_is_preserved_when_laravel_is_enabled(): void
    {
        config(['recognition.training_enabled' => true]);
        Http::fake(['*' => Http::response(['status' => 'error', 'code' => 'training_disabled'], 409)]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->postJson('/api/admin/recognition/api/train/start', ['category' => 'GREETING'])
            ->assertStatus(409)->assertJsonPath('code', 'training_disabled');
        Http::assertSentCount(1);
    }

    public function test_read_only_admin_routes_and_student_prediction_remain_available(): void
    {
        config(['recognition.training_enabled' => false]);
        Http::fake([
            '*/model_file/*' => Http::response('checkpoint'),
            '*' => Http::response(['status' => 'success', 'prediction' => 'A']),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach (['models', 'modes', 'api/train/labels', 'api/train/stats', 'api/train/status/'.str_repeat('a', 36)] as $path) {
            $this->getJson('/api/admin/recognition/'.$path)->assertOk();
        }
        $this->get('/api/admin/recognition/model_file/category/model.pt')->assertOk()->assertDownload('model.pt');
        $this->actingAs(User::factory()->create(['role' => 'user', 'is_active' => true]));
        $this->postJson('/api/recognition/predict', ['mode' => 'alphabet', 'image' => 'data:image/jpeg;base64,dGVzdA==', 'stream_id' => 'test'])
            ->assertOk()->assertJsonPath('prediction', 'A');
    }

    public function test_training_mode_does_not_bypass_admin_or_active_user_authorization(): void
    {
        Http::fake();
        $path = '/api/admin/recognition/api/train/';
        $this->getJson($path.'capabilities')->assertUnauthorized();
        foreach ([true, false] as $enabled) {
            config(['recognition.training_enabled' => $enabled]);
            foreach ([['role' => 'user', 'is_active' => true], ['role' => 'admin', 'is_active' => false]] as $attributes) {
                $this->actingAs(User::factory()->create($attributes));
                $this->getJson($path.'capabilities')->assertForbidden();
                $this->postJson($path.'start')->assertForbidden();
                $this->postJson($path.'upload_video')->assertForbidden();
            }
        }
        Http::assertNothingSent();
    }
}
