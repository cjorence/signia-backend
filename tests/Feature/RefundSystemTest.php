<?php

namespace Tests\Feature;

use App\Mail\RefundRequestAdminAlertMail;
use App\Mail\RefundRequestSubmittedMail;
use App\Mail\RefundStatusUpdatedMail;
use App\Models\PaymentTransaction;
use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundSystemTest extends TestCase
{
    use RefreshDatabase;

    private function configurePayMongo(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        config()->set('services.paymongo.api_url', 'https://api.paymongo.test');
        config()->set('services.paymongo.mode', 'test');
        config()->set('mail.default', 'array');
    }

    public function test_user_can_submit_refund_request_for_paid_purchase(): void
    {
        $this->configurePayMongo();
        Mail::fake();

        $user = User::factory()->create(['role' => 'user', 'email' => 'student@example.com']);
        PlayerProfile::create([
            'user_id' => $user->id,
            'hearts' => 5,
            'max_hearts' => 5,
            'heart_inventory' => 20,
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'heart',
            'package_key' => 'small_hearts',
            'quantity' => 10,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'provider' => 'paymongo',
            'paid_at' => now(),
        ]);

        PaymentTransaction::create([
            'purchase_id' => $purchase->id,
            'provider' => 'paymongo',
            'provider_reference' => 'cs_test_123',
            'status' => 'paid',
            'amount' => 49.00,
            'currency' => 'PHP',
            'raw_payload' => [
                'data' => [
                    'attributes' => [
                        'payments' => [
                            ['id' => 'pay_test_abc123']
                        ]
                    ]
                ]
            ],
            'processed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/user/purchases/{$purchase->id}/refund-request", [
            'reason' => 'accidental_duplicate',
            'explanation' => 'I accidentally clicked checkout twice.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '49.00');

        $this->assertDatabaseHas('refund_requests', [
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'accidental_duplicate',
            'status' => 'pending',
        ]);

        Mail::assertSent(RefundRequestSubmittedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        Mail::assertSent(RefundRequestAdminAlertMail::class);
    }

    public function test_user_cannot_request_refund_for_unpaid_or_other_users_purchase(): void
    {
        $this->configurePayMongo();
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);

        $pendingPurchase = Purchase::create([
            'user_id' => $user1->id,
            'product_type' => 'heart',
            'package_key' => 'small_hearts',
            'quantity' => 10,
            'amount' => 49.00,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user1);
        $this->postJson("/api/user/purchases/{$pendingPurchase->id}/refund-request", [
            'reason' => 'other',
        ])->assertStatus(422);

        Sanctum::actingAs($user2);
        $this->postJson("/api/user/purchases/{$pendingPurchase->id}/refund-request", [
            'reason' => 'other',
        ])->assertStatus(422);
    }

    public function test_admin_can_approve_refund_and_paymongo_is_called(): void
    {
        $this->configurePayMongo();
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@signia.test']);
        $user = User::factory()->create(['role' => 'user', 'email' => 'customer@signia.test']);
        $profile = PlayerProfile::create([
            'user_id' => $user->id,
            'hearts' => 5,
            'max_hearts' => 5,
            'heart_inventory' => 20,
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'heart',
            'package_key' => 'small_hearts',
            'quantity' => 10,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'provider' => 'paymongo',
            'paid_at' => now(),
        ]);

        PaymentTransaction::create([
            'purchase_id' => $purchase->id,
            'provider' => 'paymongo',
            'provider_reference' => 'cs_test_123',
            'status' => 'paid',
            'amount' => 49.00,
            'currency' => 'PHP',
            'raw_payload' => [
                'data' => [
                    'attributes' => [
                        'payments' => [
                            ['id' => 'pay_test_xyz789']
                        ]
                    ]
                ]
            ],
            'processed_at' => now(),
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'accidental_duplicate',
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_paymongo_mock_123',
                    'type' => 'refund',
                    'attributes' => [
                        'amount' => 4900,
                        'status' => 'succeeded',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/approve", [
            'admin_notes' => 'Verified accidental duplicate charge.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.paymongo_refund_id', 'ref_paymongo_mock_123');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'refunded',
        ]);

        // Verify hearts were deducted
        $this->assertEquals(10, $profile->fresh()->heart_inventory);

        Mail::assertSent(RefundStatusUpdatedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_admin_can_reject_refund(): void
    {
        $this->configurePayMongo();
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'email' => 'customer2@signia.test']);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'heart',
            'package_key' => 'small_hearts',
            'quantity' => 10,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'other',
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/reject", [
            'reason' => 'Hearts have already been consumed.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('refund_requests', [
            'id' => $refundRequest->id,
            'status' => 'rejected',
            'admin_notes' => 'Hearts have already been consumed.',
        ]);

        Mail::assertSent(RefundStatusUpdatedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
