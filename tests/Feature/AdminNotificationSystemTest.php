<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Support\LegalAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminNotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->player = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        Cache::forget(AdminNotificationService::SETTINGS_KEY);
    }

    public function test_non_admin_cannot_access_notification_endpoints(): void
    {
        $response = $this->actingAs($this->player)->getJson('/api/admin/notifications');
        $response->assertStatus(403);

        $response = $this->actingAs($this->player)->getJson('/api/admin/notifications/settings');
        $response->assertStatus(403);
    }

    public function test_admin_can_get_and_update_notification_settings(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/notifications/settings');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'newUserAlerts' => true,
                    'purchaseAlerts' => true,
                    'trainingAlerts' => true,
                ],
            ]);

        $updateResponse = $this->actingAs($this->admin)->patchJson('/api/admin/notifications/settings', [
            'newUserAlerts' => false,
            'purchaseAlerts' => true,
            'trainingAlerts' => false,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'newUserAlerts' => false,
                    'purchaseAlerts' => true,
                    'trainingAlerts' => false,
                ],
            ]);
    }

    public function test_user_registration_creates_admin_notification_when_enabled(): void
    {
        $service = app(AdminNotificationService::class);
        $service->updateSettings(['newUserAlerts' => true]);

        $registerPayload = [
            'name' => 'Test Learner',
            'email' => 'newlearner@signia.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => LegalAgreement::TERMS_VERSION,
            'privacy_version' => LegalAgreement::PRIVACY_VERSION,
        ];

        $res = $this->postJson('/api/auth/register', $registerPayload);
        $res->assertStatus(201);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'user_registered',
            'title' => 'New Learner Registration',
        ]);

        $notification = AdminNotification::where('type', 'user_registered')->first();
        $this->assertStringContainsString('newlearner@signia.test', $notification->message);
    }

    public function test_user_registration_does_not_notify_when_disabled(): void
    {
        $service = app(AdminNotificationService::class);
        $service->updateSettings(['newUserAlerts' => false]);

        $registerPayload = [
            'name' => 'Silent Learner',
            'email' => 'silent@signia.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => LegalAgreement::TERMS_VERSION,
            'privacy_version' => LegalAgreement::PRIVACY_VERSION,
        ];

        $res = $this->postJson('/api/auth/register', $registerPayload);
        $res->assertStatus(201);

        $this->assertDatabaseMissing('admin_notifications', [
            'type' => 'user_registered',
        ]);
    }

    public function test_heart_purchase_creates_admin_notification_when_enabled(): void
    {
        $service = app(AdminNotificationService::class);
        $service->updateSettings(['purchaseAlerts' => true]);

        $purchase = Purchase::create([
            'user_id' => $this->player->id,
            'package_key' => 'refill_5',
            'amount' => 4.99,
            'currency' => 'USD',
            'status' => 'paid',
            'quantity' => 5,
        ]);

        $service->notifyHeartPurchase($purchase, $this->player);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'heart_purchase',
            'title' => 'Hearts Store Purchase',
        ]);

        $notif = AdminNotification::where('type', 'heart_purchase')->first();
        $this->assertStringContainsString('4.99', $notif->message);
        $this->assertStringContainsString($this->player->name, $notif->message);
    }

    public function test_legacy_training_callback_is_acknowledged_without_creating_unverified_alert(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/notifications/training-completed', [
            'category' => 'Greetings',
            'accuracy' => 0.965,
            'model_path' => 'models/greetings_lstm.pt',
        ]);

        $response->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseCount('admin_notifications', 0);
    }

    public function test_admin_can_manage_notifications_read_and_delete(): void
    {
        $notif1 = AdminNotification::create([
            'type' => 'user_registered',
            'title' => 'First Notification',
            'message' => 'First message',
            'severity' => 'info',
        ]);

        $notif2 = AdminNotification::create([
            'type' => 'heart_purchase',
            'title' => 'Second Notification',
            'message' => 'Second message',
            'severity' => 'success',
        ]);

        $list = $this->actingAs($this->admin)->getJson('/api/admin/notifications');
        $list->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 2,
            ]);

        // Mark notif1 as read
        $markRes = $this->actingAs($this->admin)->postJson("/api/admin/notifications/{$notif1->id}/read");
        $markRes->assertStatus(200);
        $this->assertNotNull($notif1->fresh()->read_at);

        // Check unread count is now 1
        $list = $this->actingAs($this->admin)->getJson('/api/admin/notifications');
        $list->assertJson(['unread_count' => 1]);

        // Mark all as read
        $this->actingAs($this->admin)->postJson('/api/admin/notifications/read-all')->assertStatus(200);
        $this->assertNotNull($notif2->fresh()->read_at);

        // Delete one
        $this->actingAs($this->admin)->deleteJson("/api/admin/notifications/{$notif1->id}")->assertStatus(200);
        $this->assertDatabaseMissing('admin_notifications', ['id' => $notif1->id]);

        // Clear all
        $this->actingAs($this->admin)->postJson('/api/admin/notifications/clear-all')->assertStatus(200);
        $this->assertDatabaseCount('admin_notifications', 0);
    }
}
