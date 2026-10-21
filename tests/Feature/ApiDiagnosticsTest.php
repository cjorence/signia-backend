<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDiagnosticsTest extends TestCase
{
    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->entries[] = ['message' => $event->message, 'context' => $event->context];
        });
    }

    public function test_production_exception_has_correlated_safe_diagnostics(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false]);
        Route::middleware('api')->post('/api/diagnostic-test/{item}', function (Request $request) {
            $user = new User;
            $user->id = 42;
            $request->setUserResolver(fn () => $user);
            throw new \RuntimeException('private-exception-token');
        });

        $response = $this->withHeaders(['Authorization' => 'Bearer private-header-token'])
            ->postJson('/api/diagnostic-test/private-path-value?secret=private-query-value', [
                'password' => 'private-password-value', 'image' => 'private-camera-value',
            ]);
        $response->assertStatus(500)->assertHeader('X-Request-ID');
        $id = $response->headers->get('X-Request-ID');
        $response->assertJsonPath('request_id', $id)->assertDontSee('private-exception-token');

        $exceptions = array_values(array_filter($this->entries, fn ($entry) => $entry['message'] === 'API exception'));
        $this->assertCount(1, $exceptions);
        $this->assertSame($id, $exceptions[0]['context']['request_id']);
        $this->assertSame('api/diagnostic-test/{item}', $exceptions[0]['context']['route']);
        $this->assertSame(42, $exceptions[0]['context']['user_id']);
        $this->assertSame(\RuntimeException::class, $exceptions[0]['context']['exception_type']);
        $this->assertNotEmpty($exceptions[0]['context']['trace']);
        $serialized = json_encode($this->entries);
        foreach (['private-exception-token', 'private-header-token', 'private-path-value', 'private-query-value', 'private-password-value', 'private-camera-value'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $failures = array_values(array_filter($this->entries, fn ($entry) => $entry['message'] === 'API request failed'));
        $this->assertCount(1, $failures);
        $this->assertSame($id, $failures[0]['context']['request_id']);
        $this->assertSame(500, $failures[0]['context']['status']);
        $this->assertGreaterThanOrEqual(0, $failures[0]['context']['duration_ms']);
    }

    public function test_handled_failure_is_logged_and_request_context_does_not_leak(): void
    {
        Route::middleware('api')->get('/api/diagnostic-unavailable', fn () => response()->json(['message' => 'Please retry.'], 503));
        Route::middleware('api')->get('/api/diagnostic-ok', fn () => response()->json(['ok' => true]));
        $failed = $this->withHeaders(['X-Request-ID' => 'caller-chosen-id'])->getJson('/api/diagnostic-unavailable');
        $failed->assertStatus(503)->assertJsonPath('message', 'Please retry.');
        $id = $failed->headers->get('X-Request-ID');
        $this->assertNotSame('caller-chosen-id', $id);
        $failed->assertJsonPath('request_id', $id);
        $ok = $this->getJson('/api/diagnostic-ok')->assertOk()->assertExactJson(['ok' => true]);
        $this->assertNotSame($id, $ok->headers->get('X-Request-ID'));
        $failures = array_filter($this->entries, fn ($entry) => $entry['message'] === 'API request failed');
        $this->assertCount(1, $failures);
        Log::info('After request');
        $this->assertArrayNotHasKey('request_id', end($this->entries)['context']);
    }

    public function test_validation_errors_keep_their_existing_shape_without_failure_noise(): void
    {
        Route::middleware('api')->post('/api/diagnostic-validation', function (Request $request) {
            $request->validate(['name' => ['required']]);
        });
        $this->postJson('/api/diagnostic-validation', [])->assertStatus(422)
            ->assertJsonValidationErrors('name')->assertHeader('X-Request-ID');
        $this->assertCount(0, array_filter($this->entries, fn ($entry) => in_array($entry['message'], ['API exception', 'API request failed'], true)));
    }
}
