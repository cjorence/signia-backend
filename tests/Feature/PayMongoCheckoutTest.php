<?php

namespace Tests\Feature;

use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayMongoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function configurePayMongo(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        config()->set('services.paymongo.api_url', 'https://api.paymongo.test');
        config()->set('services.paymongo.mode', 'test');
        config()->set('services.paymongo.webhook_secret', 'whsec_test_example');
        config()->set('services.paymongo.frontend_url', 'http://localhost:3000');
    }

    public function test_player_can_create_a_hosted_checkout_session(): void
    {
        $this->configurePayMongo();
        $player = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $player->id, 'hearts' => 5, 'max_hearts' => 5]);

        Http::fake([
            'https://api.paymongo.test/v2/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_heart_pack',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/session'],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($player);

        $this->postJson('/api/user/purchases/checkout', ['package_key' => 'medium_hearts'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.test/session')
            ->assertJsonPath('data.purchase.checkout_session_id', 'cs_test_heart_pack');

        $this->assertDatabaseHas('purchases', [
            'user_id' => $player->id,
            'provider' => 'paymongo',
            'checkout_session_id' => 'cs_test_heart_pack',
            'status' => 'pending',
        ]);
    }

    public function test_valid_paid_webhook_credits_inventory_once(): void
    {
        $this->configurePayMongo();
        $player = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $player->id, 'hearts' => 2, 'max_hearts' => 5, 'heart_inventory' => 0]);
        $purchase = Purchase::create([
            'user_id' => $player->id,
            'product_type' => 'hearts',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49,
            'currency' => 'PHP',
            'status' => 'pending',
            'provider' => 'paymongo',
            'checkout_session_id' => 'cs_test_paid',
        ]);

        $payload = json_encode([
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'livemode' => false,
                    'data' => [
                        'id' => 'cs_test_paid',
                        'attributes' => ['reference_number' => 'purchase:'.$purchase->id],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_example');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',te='.$signature.',li=',
        ];

        $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $payload)
            ->assertOk();

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'paid']);
        $this->assertSame(5, $player->playerProfile->fresh()->heart_inventory);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('heart_transactions', 1);
    }

    public function test_invalid_webhook_signature_cannot_credit_inventory(): void
    {
        $this->configurePayMongo();
        $player = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $player->id, 'hearts' => 2, 'max_hearts' => 5, 'heart_inventory' => 0]);

        $payload = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid']]], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't='.now()->timestamp.',te=invalid,li=',
        ], $payload)->assertUnauthorized();

        $this->assertSame(0, $player->playerProfile->fresh()->heart_inventory);
    }
}
