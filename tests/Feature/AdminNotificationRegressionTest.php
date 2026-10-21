<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminNotificationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const JOB = '12345678-1234-1234-1234-123456789abc';

    private function statusUrl(string $job = self::JOB): string
    {
        return '/api/admin/recognition/api/train/status/'.$job;
    }

    private function fakeTrainingStatus(string $status = 'completed', int $httpStatus = 200): void
    {
        config(['recognition.url' => 'http://recognition.internal']);
        Http::fake(['*' => Http::response(['job' => [
            'status' => $status,
            'category' => 'COLOR',
            'results' => ['best_val_accuracy' => 0.95, 'model_path' => 'artifacts/models/color/color_lstm_best.pt'],
        ]], $httpStatus)]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    public function test_completed_job_is_delivered_once_across_polls_callbacks_and_dismissal(): void
    {
        $this->fakeTrainingStatus();
        $this->getJson($this->statusUrl())->assertOk();
        $this->getJson($this->statusUrl())->assertOk();
        $this->postJson('/api/admin/notifications/training-completed', ['category' => 'COLOR', 'accuracy' => 0.95])->assertOk();
        $this->assertDatabaseCount('admin_notifications', 1);
        $notification = AdminNotification::firstOrFail();
        $this->assertSame(self::JOB, $notification->data['job_id']);
        $this->assertStringContainsString('95%', $notification->message);

        $this->deleteJson('/api/admin/notifications/'.$notification->id)->assertOk();
        Cache::flush();
        $this->getJson($this->statusUrl())->assertOk();
        $this->assertDatabaseCount('admin_notifications', 0);
        $this->assertDatabaseCount('admin_notification_events', 1);

        // A new job for the same category/checkpoint is a distinct completion.
        $this->getJson($this->statusUrl('12345678-1234-1234-1234-123456789abd'))->assertOk();
        $this->assertDatabaseCount('admin_notifications', 1);
        $this->postJson('/api/admin/notifications/clear-all')->assertOk();
        $this->getJson($this->statusUrl('12345678-1234-1234-1234-123456789abd'))->assertOk();
        $this->assertDatabaseCount('admin_notifications', 0);
    }

    public function test_running_failed_and_unsuccessful_status_responses_do_not_notify(): void
    {
        foreach ([['running', 200], ['failed', 200], ['completed', 500], ['completed', 404]] as [$status, $code]) {
            $this->fakeTrainingStatus($status, $code);
            $this->getJson($this->statusUrl());
        }
        $this->assertDatabaseCount('admin_notifications', 0);
        $this->assertDatabaseCount('admin_notification_events', 0);
    }

    public function test_disabled_alerts_are_respected_after_cache_clear_and_partial_updates(): void
    {
        $this->fakeTrainingStatus();
        $service = app(AdminNotificationService::class);
        $service->updateSettings(['trainingAlerts' => false, 'purchaseAlerts' => false]);
        Cache::flush();
        $this->getJson('/api/admin/notifications/settings')->assertOk()
            ->assertJsonPath('data.trainingAlerts', false)
            ->assertJsonPath('data.purchaseAlerts', false);
        $service->updateSettings(['newUserAlerts' => false]);
        $this->assertSame(['newUserAlerts' => false, 'purchaseAlerts' => false, 'trainingAlerts' => false], $service->getSettings());
        $this->getJson($this->statusUrl())->assertOk();
        $this->assertDatabaseCount('admin_notifications', 0);
    }

    public function test_purchases_use_their_stored_currency_without_changing_the_amount(): void
    {
        $user = User::factory()->create();
        foreach (['PHP', 'USD'] as $currency) {
            $purchase = Purchase::create([
                'user_id' => $user->id, 'package_key' => 'small_hearts',
                'amount' => 49, 'currency' => $currency, 'status' => 'paid', 'quantity' => 5,
            ]);
            $notification = app(AdminNotificationService::class)->notifyHeartPurchase($purchase, $user);
            $this->assertStringContainsString($currency.' 49.00', $notification->message);
            $this->assertSame($currency, $notification->data['currency']);
            $this->assertSame('49.00', $purchase->fresh()->amount);
        }
    }

    public function test_receipt_is_rolled_back_if_notification_insert_fails(): void
    {
        $service = app(AdminNotificationService::class);
        Event::listen('eloquent.creating: '.AdminNotification::class, function () {
            throw new \RuntimeException('Simulated insert failure');
        });
        try {
            $service->notifyTrainingComplete(self::JOB, 'COLOR', 0.95);
            $this->fail('Expected the simulated insert failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated insert failure', $error->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AdminNotification::class);
        }
        $this->assertDatabaseCount('admin_notification_events', 0);
        $service->notifyTrainingComplete(self::JOB, 'COLOR', 0.95);
        $this->assertDatabaseCount('admin_notifications', 1);
    }

    public function test_migration_imports_existing_cache_preferences_and_preserves_other_tables(): void
    {
        // Only this test's in-memory database is modified.
        $migration = require database_path('migrations/2026_10_21_190000_persist_admin_notification_settings_and_events.php');
        $migration->down();
        Cache::forever(AdminNotificationService::SETTINGS_KEY, ['trainingAlerts' => false, 'newUserAlerts' => false]);
        try {
            $migration->up();
            Cache::flush();
            $this->assertSame(['newUserAlerts' => false, 'purchaseAlerts' => true, 'trainingAlerts' => false], app(AdminNotificationService::class)->getSettings());
            $this->assertTrue(Schema::hasTable('admin_notifications'));
            $this->assertTrue(Schema::hasTable('users'));
        } finally {
            Cache::forget(AdminNotificationService::SETTINGS_KEY);
        }
    }
}
