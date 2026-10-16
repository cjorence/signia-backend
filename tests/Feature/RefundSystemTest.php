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

    public function test_admin_can_approve_refund_and_hearts_safely_reclaimed_without_negative_balance(): void
    {
        $this->configurePayMongo();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        // User bought 10 hearts but only has 3 remaining (consumed 7)
        $profile = PlayerProfile::create([
            'user_id' => $user->id,
            'hearts' => 3,
            'max_hearts' => 5,
            'heart_inventory' => 0,
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
            'provider_reference' => 'cs_test_consumed',
            'status' => 'paid',
            'amount' => 49.00,
            'currency' => 'PHP',
            'raw_payload' => ['data' => ['attributes' => ['payments' => [['id' => 'pay_test_consumed']]]]],
            'processed_at' => now(),
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'accidental_purchase',
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_consumed_override',
                    'type' => 'refund',
                    'attributes' => ['amount' => 4900, 'status' => 'succeeded'],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/approve", [
            'admin_notes' => 'Customer service goodwill approval.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'refunded');

        // Remaining 3 hearts are drained to 0, never negative
        $this->assertEquals(0, $profile->fresh()->hearts);
        $this->assertEquals(0, $profile->fresh()->heart_inventory);
    }

    public function test_story_chapter_refund_automatically_revokes_unlock(): void
    {
        $this->configurePayMongo();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $story = \App\Models\Story::create([
            'title' => 'Chapter 2',
            'slug' => 'chapter-2',
            'order' => 2,
            'description' => 'Test chapter',
            'is_free' => false,
            'price' => 49.00,
            'currency' => 'PHP',
            'status' => 'published',
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'story',
            'package_key' => 'story_ch_2',
            'quantity' => 1,
            'story_id' => $story->id,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'provider' => 'paymongo',
            'paid_at' => now(),
        ]);

        // Initially unlocked
        \App\Models\UserStoryUnlock::create([
            'user_id' => $user->id,
            'story_id' => $story->id,
            'purchase_id' => $purchase->id,
            'unlocked_at' => now(),
        ]);

        $this->assertDatabaseHas('user_story_unlocks', [
            'user_id' => $user->id,
            'story_id' => $story->id,
        ]);

        PaymentTransaction::create([
            'purchase_id' => $purchase->id,
            'provider' => 'paymongo',
            'provider_reference' => 'cs_test_story_refund',
            'status' => 'paid',
            'amount' => 49.00,
            'currency' => 'PHP',
            'raw_payload' => ['data' => ['attributes' => ['payments' => [['id' => 'pay_test_story']]]]],
            'processed_at' => now(),
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'technical_issue',
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => Http::response([
                'data' => [
                    'id' => 'ref_story_lock_test',
                    'type' => 'refund',
                    'attributes' => ['amount' => 4900, 'status' => 'succeeded'],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/approve", [
            'admin_notes' => 'Technical story issue approved.',
        ]);

        $response->assertStatus(200);

        // Verification: UserStoryUnlock record is completely deleted!
        $this->assertDatabaseMissing('user_story_unlocks', [
            'user_id' => $user->id,
            'story_id' => $story->id,
        ]);
    }

    public function test_admin_can_approve_refund_with_cut_off_amount(): void
    {
        $this->configurePayMongo();
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user', 'email' => 'partial@signia.test']);
        PlayerProfile::create([
            'user_id' => $user->id,
            'hearts' => 5,
            'max_hearts' => 5,
            'heart_inventory' => 10,
        ]);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'heart',
            'package_key' => 'small_hearts',
            'quantity' => 5,
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'paid',
            'provider' => 'paymongo',
            'paid_at' => now(),
        ]);

        PaymentTransaction::create([
            'purchase_id' => $purchase->id,
            'provider' => 'paymongo',
            'provider_reference' => 'cs_test_cutoff',
            'status' => 'paid',
            'amount' => 49.00,
            'currency' => 'PHP',
            'raw_payload' => ['data' => ['attributes' => ['payments' => [['id' => 'pay_test_cutoff']]]]],
            'processed_at' => now(),
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'reason' => 'accidental_purchase',
            'amount' => 49.00,
            'currency' => 'PHP',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.paymongo.test/v1/refunds' => function ($request) {
                // Assert PayMongo receives 80% (3920 centavos)
                $data = $request->data();
                $this->assertEquals(3920, data_get($data, 'data.attributes.amount'));
                return Http::response([
                    'data' => [
                        'id' => 'ref_partial_123',
                        'type' => 'refund',
                        'attributes' => ['amount' => 3920, 'status' => 'succeeded'],
                    ],
                ], 200);
            },
        ]);

        Sanctum::actingAs($admin);

        // 20% cut-off -> 39.20
        $response = $this->postJson("/api/admin/refund-requests/{$refundRequest->id}/approve", [
            'approved_amount' => 39.20,
            'admin_notes' => 'Approved with 20% policy cut-off.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.amount', '39.20');

        $this->assertEquals(39.20, (float) $refundRequest->fresh()->amount);
        $this->assertEquals('ref_partial_123', $refundRequest->fresh()->paymongo_refund_id);
    }
}
