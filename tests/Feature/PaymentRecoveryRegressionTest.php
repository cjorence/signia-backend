<?php

namespace Tests\Feature;

use App\Models\{User, Purchase, PaymentTransaction, PlayerProfile, RefundRequest};
use App\Services\{RefundService, PaymentService, PurchaseService};
use App\Mail\RefundStatusUpdatedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Mail};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentRecoveryRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Mail::fake();
        config(['services.paymongo.secret_key' => 'audit', 'services.paymongo.api_url' => 'https://audit.invalid', 'services.paymongo.mode' => 'test']);
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        PlayerProfile::create(['user_id' => $user->id, 'hearts' => 5, 'max_hearts' => 5, 'heart_inventory' => 5]);
        $purchase = Purchase::create(['user_id' => $user->id, 'product_type' => 'hearts', 'package_key' => 'small_hearts', 'quantity' => 5, 'amount' => 100, 'currency' => 'PHP', 'status' => 'paid', 'provider' => 'paymongo', 'paid_at' => now()]);
        PaymentTransaction::create(['purchase_id' => $purchase->id, 'provider' => 'paymongo', 'provider_reference' => 'pay_audit', 'status' => 'paid', 'amount' => 100, 'currency' => 'PHP', 'raw_payload' => ['data' => ['id' => 'pay_audit']]]);
        $refund = RefundRequest::create(['user_id' => $user->id, 'purchase_id' => $purchase->id, 'reason' => 'accidental_purchase', 'amount' => 100, 'currency' => 'PHP', 'status' => 'pending']);
        return [$admin, $purchase, $refund];
    }

    public function test_unknown_retry_rejects_changed_amount_and_preserves_original_attempt(): void
    {
        [$admin, , $refund] = $this->fixture();
        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $calls[] = [$request->header('Idempotency-Key'), data_get($request->data(), 'data.attributes.amount')];
            return Http::response([], 500);
        });
        app(RefundService::class)->approve($refund, $admin, null, 80);
        try {
            app(RefundService::class)->approve($refund->fresh(), $admin, null, 64);
            $this->fail('Changed retry amount must be rejected');
        } catch (ValidationException $e) {
            $this->assertCount(1, $calls);
            $this->assertEquals(80, $refund->fresh()->amount);
        }
        app(RefundService::class)->approve($refund->fresh(), $admin, null, 80);
        $this->assertSame($calls[0], $calls[1]);
    }

    public function test_provider_rejection_restores_pending_amount_and_attempt_key(): void
    {
        [$admin, , $refund] = $this->fixture();
        Http::fake(fn () => Http::response([], 422));
        try {
            app(RefundService::class)->approve($refund, $admin, null, 80);
            $this->fail('Expected rejection');
        } catch (ValidationException $e) {
            $this->assertSame('pending', $refund->fresh()->status);
            $this->assertEquals(100, $refund->fresh()->amount);
            $this->assertNull($refund->fresh()->provider_request_key);
        }
    }

    public function test_stale_and_previously_failed_paid_orders_recover_exactly_once(): void
    {
        [, $purchase] = $this->fixture();
        $purchase->paymentTransactions()->delete();
        Purchase::whereKey($purchase->id)->update(['status' => 'pending', 'checkout_session_id' => 'cs_audit', 'created_at' => now()->subHours(25)]);
        Http::fake(fn () => Http::response(['data' => ['attributes' => ['payments' => [['id' => 'pay_recovered', 'attributes' => ['status' => 'paid']]]]]], 200));
        $this->assertSame('paid', app(PaymentService::class)->verifyCheckoutSession($purchase->fresh())->status);
        $this->assertSame('paid', app(PaymentService::class)->verifyCheckoutSession($purchase->fresh())->status);
        $this->assertEquals(10, $purchase->user->playerProfile->fresh()->heart_inventory);
        $this->assertSame(1, $purchase->paymentTransactions()->count());

        $legacy = $purchase->replicate(['provider_reference', 'paid_at']);
        $legacy->fill(['status' => 'failed', 'checkout_session_id' => 'cs_legacy'])->save();
        $this->assertSame('paid', app(PaymentService::class)->verifyCheckoutSession($legacy)->status);
        $this->assertEquals(15, $purchase->user->playerProfile->fresh()->heart_inventory);
    }

    public function test_provider_outage_or_open_session_does_not_expire_a_hosted_order(): void
    {
        [, $purchase] = $this->fixture();
        Purchase::whereKey($purchase->id)->update(['status' => 'pending', 'checkout_session_id' => 'cs_audit', 'created_at' => now()->subHours(25)]);
        Http::fakeSequence()->push([], 503)->push(['data' => ['attributes' => ['status' => 'active', 'payments' => []]]]);
        $this->assertSame('pending', app(PaymentService::class)->verifyCheckoutSession($purchase->fresh())->status);
        $this->assertSame('pending', app(PaymentService::class)->verifyCheckoutSession($purchase->fresh())->status);
        Sanctum::actingAs($purchase->user);
        $this->getJson('/api/user/purchases')->assertOk();
        $this->getJson('/api/user/purchases/'.$purchase->id)->assertOk();
        $this->assertSame('pending', $purchase->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_scheduled_reconciliation_recovers_a_paid_order_without_a_browser_visit(): void
    {
        [, $purchase] = $this->fixture();
        $purchase->paymentTransactions()->delete();
        Purchase::whereKey($purchase->id)->update(['status' => 'pending', 'checkout_session_id' => 'cs_audit', 'created_at' => now()->subHours(25)]);
        Http::fake(fn () => Http::response(['data' => ['attributes' => ['payments' => [['id' => 'pay_recovered', 'attributes' => ['status' => 'paid']]]]]], 200));
        app(PurchaseService::class)->expireStalePendingPurchases(true);
        $this->assertSame('paid', $purchase->fresh()->status);
    }

    public function test_expiration_does_not_overwrite_a_concurrent_paid_webhook(): void
    {
        [, $purchase] = $this->fixture();
        $purchase->update(['status' => 'pending', 'checkout_session_id' => 'cs_audit']);
        Http::fake(function () use ($purchase) {
            Purchase::whereKey($purchase->id)->update(['status' => 'paid']);
            return Http::response(['data' => ['attributes' => ['status' => 'expired', 'payments' => []]]]);
        });
        $this->assertSame('paid', app(PaymentService::class)->verifyCheckoutSession($purchase)->status);
    }

    public function test_unconfirmed_refund_emails_do_not_promise_payout(): void
    {
        [, , $refund] = $this->fixture();
        foreach (['failed', 'unknown'] as $status) {
            $refund->update(['status' => 'approved', 'provider_status' => $status]);
            $mail = new RefundStatusUpdatedMail($refund->fresh());
            $this->assertStringContainsString('Needs Attention', $mail->envelope()->subject);
            $this->assertStringContainsString('has not been confirmed', $mail->content()->htmlString);
            $this->assertStringNotContainsString('5 to 10 banking days', $mail->content()->htmlString);
        }
        foreach (['pending', 'processing'] as $status) {
            $refund->update(['provider_status' => $status]);
            $this->assertStringContainsString('5 to 10 banking days', (new RefundStatusUpdatedMail($refund->fresh()))->content()->htmlString);
        }
    }
}
