<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_pending_purchases_automatically_expire_to_failed_on_player_fetch(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $player->id, 'hearts' => 5, 'max_hearts' => 5]);

        // Stale pending purchase (25 hours ago)
        $stalePurchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
        Purchase::where('id', $stalePurchase->id)->update(['created_at' => now()->subHours(25)]);

        // Recent pending purchase (2 hours ago)
        $recentPurchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'medium_hearts',
            'quantity' => 15,
            'amount' => 129.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
        Purchase::where('id', $recentPurchase->id)->update(['created_at' => now()->subHours(2)]);

        // Paid purchase (30 hours ago)
        $paidPurchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'large_hearts',
            'quantity' => 30,
            'amount' => 249.00,
            'currency' => 'PHP',
            'status' => 'paid',
        ]);
        Purchase::where('id', $paidPurchase->id)->update(['created_at' => now()->subHours(30)]);

        Sanctum::actingAs($player);

        $response = $this->getJson('/api/user/purchases');
        $response->assertOk();

        // Stale purchase transitioned to failed
        $this->assertSame('failed', $stalePurchase->fresh()->status);
        // Recent pending purchase remains pending
        $this->assertSame('pending', $recentPurchase->fresh()->status);
        // Paid purchase remains paid
        $this->assertSame('paid', $paidPurchase->fresh()->status);
    }

    public function test_admin_purchases_and_revenue_summary_auto_expire_stale_purchases(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'user']);

        $purchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
        Purchase::where('id', $purchase->id)->update(['created_at' => now()->subHours(48)]);

        Sanctum::actingAs($admin);

        $summaryRes = $this->getJson('/api/admin/revenue/summary');
        $summaryRes->assertOk()
            ->assertJsonPath('data.pending_purchases', 0)
            ->assertJsonPath('data.failed_purchases', 1);

        $allRes = $this->getJson('/api/admin/purchases');
        $allRes->assertOk();
        $this->assertSame('failed', $allRes->json('data.0.status'));
    }

    public function test_artisan_command_expires_stale_pending_purchases(): void
    {
        $player = User::factory()->create(['role' => 'user']);

        $purchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);
        Purchase::where('id', $purchase->id)->update(['created_at' => now()->subHours(30)]);

        $exitCode = Artisan::call('purchases:expire-stale');
        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('purchases', ['status' => 'pending']);
        $this->assertDatabaseHas('purchases', ['status' => 'failed']);
    }

    public function test_verify_endpoint_auto_expires_stale_purchase(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $purchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
            'checkout_session_id' => 'cs_test_stale_verify',
        ]);
        Purchase::where('id', $purchase->id)->update(['created_at' => now()->subHours(26)]);

        Sanctum::actingAs($player);

        $this->postJson("/api/user/purchases/{$purchase->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame('failed', $purchase->fresh()->status);
    }

    public function test_webhook_can_safely_fulfill_if_auto_failed_at_boundary(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $player->id, 'hearts' => 5, 'max_hearts' => 5]);

        $purchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'checkout_session_id' => 'cs_test_boundary',
            'status' => 'failed',
        ]);
        Purchase::where('id', $purchase->id)->update(['created_at' => now()->subHours(25)]);

        $fakeEvent = [
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'cs_test_boundary',
                        'attributes' => [
                            'reference_number' => 'purchase:'.$purchase->id,
                            'payments' => [
                                [
                                    'id' => 'pay_boundary_123',
                                    'attributes' => [
                                        'status' => 'paid',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        config()->set('services.paymongo.mode', 'test');
        $fulfilled = app(\App\Services\PaymentService::class)->fulfillCheckoutPayment($fakeEvent);

        $this->assertNotNull($fulfilled);
        $this->assertSame('paid', $fulfilled->status);
        $this->assertSame(5, $player->playerProfile->fresh()->heart_inventory);
    }
}
