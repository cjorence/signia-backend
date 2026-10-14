<?php

namespace Tests\Feature;

use App\Models\HeartTransaction;
use App\Models\PaymentTransaction;
use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refunding_usable_hearts_keeps_automatic_regeneration_working(): void
    {
        [$admin, $user, $profile, , $request] = $this->order();
        $profile->update(['heart_inventory' => 0, 'next_heart_at' => null]);
        Http::fake(['*' => Http::response(['data' => ['id' => 'ref_meter', 'attributes' => ['status' => 'succeeded']]])]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertOk();
        $this->assertEquals(0, $profile->fresh()->hearts);
        $this->assertNotNull($profile->fresh()->next_heart_at);
        $this->travel(31)->minutes();
        Sanctum::actingAs($user);
        $this->getJson('/api/user/hearts')->assertOk()->assertJsonPath('data.hearts', 1);
    }

    public function test_admin_grant_preserves_overflow_and_is_visible_in_player_balance_and_activity(): void
    {
        [$admin, $user, $profile] = $this->order();
        $profile->update(['hearts' => 3, 'heart_inventory' => 0]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/users/{$user->id}/hearts/grant", ['amount' => 8, 'reason' => 'Replacement'])
            ->assertOk()->assertJsonPath('data.hearts', 5)->assertJsonPath('data.heart_inventory', 6);
        Sanctum::actingAs($user);
        $this->getJson('/api/user/hearts')->assertOk()->assertJsonPath('data.hearts', 5)->assertJsonPath('data.heart_inventory', 6);
        $this->getJson('/api/user/hearts/transactions')->assertOk()
            ->assertJsonPath('data.0.type', 'admin_grant')->assertJsonPath('data.0.amount', 8)
            ->assertJsonPath('data.0.metadata.inventory_hearts_added', 6);
    }

    private function order(): array
    {
        config(['services.paymongo.secret_key' => 'sk_test_example', 'services.paymongo.api_url' => 'https://api.paymongo.test']);
        Mail::fake();
        Cache::flush();
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $profile = PlayerProfile::create(['user_id' => $user->id, 'hearts' => 5, 'max_hearts' => 5, 'heart_inventory' => 10]);
        $purchase = Purchase::create(['user_id' => $user->id, 'product_type' => 'heart', 'package_key' => 'small_hearts', 'quantity' => 5, 'amount' => 49, 'currency' => 'PHP', 'status' => 'paid', 'paid_at' => now()]);
        PaymentTransaction::create(['purchase_id' => $purchase->id, 'provider' => 'paymongo', 'provider_reference' => 'pay_test_order', 'status' => 'paid', 'amount' => 49, 'currency' => 'PHP', 'raw_payload' => ['data' => ['id' => 'pay_test_order']]]);
        $request = RefundRequest::create(['user_id' => $user->id, 'purchase_id' => $purchase->id, 'reason' => 'other', 'amount' => 49, 'currency' => 'PHP', 'status' => 'pending']);

        return [$admin, $user, $profile, $purchase, $request];
    }

    public function test_admin_purchase_and_heart_responses_include_customers_without_leaking_other_users_orders(): void
    {
        [$admin, $user, $profile, $purchase] = $this->order();
        HeartTransaction::create(['user_id' => $user->id, 'type' => 'admin_grant', 'amount' => 2, 'reason' => 'Replacement']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/purchases')->assertOk()->assertJsonPath('data.0.user.email', $user->email);
        $this->getJson('/api/admin/heart-transactions')->assertOk()->assertJsonPath('data.0.user.name', $user->name);
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/user/purchases')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/user/refund-requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/purchases')->assertForbidden();
    }

    public function test_provider_failure_never_marks_purchase_refunded_or_deducts_hearts(): void
    {
        [$admin, , $profile, $purchase, $request] = $this->order();
        Http::fake(['*' => Http::response(['errors' => [['code' => 'insufficient_balance']]], 422)]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertUnprocessable();
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertEquals(10, $profile->fresh()->heart_inventory);
        $this->assertDatabaseCount('heart_transactions', 0);
    }

    public function test_missing_credentials_cannot_simulate_a_refund(): void
    {
        [$admin, , , $purchase, $request] = $this->order();
        config(['services.paymongo.secret_key' => '']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertUnprocessable();
        $this->assertNull($request->fresh()->paymongo_refund_id);
        $this->assertSame('paid', $purchase->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_processing_refund_reconciles_once_across_player_history_revenue_and_hearts(): void
    {
        [$admin, $user, $profile, $purchase, $request] = $this->order();
        Http::fake([
            '*/v1/refunds' => Http::response(['data' => ['id' => 'ref_processing', 'attributes' => ['status' => 'pending']]]),
            '*/v1/refunds/ref_processing' => Http::response(['data' => ['id' => 'ref_processing', 'attributes' => ['status' => 'succeeded']]]),
        ]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.provider_status', 'pending');
        $this->assertEquals(10, $profile->fresh()->heart_inventory);
        Sanctum::actingAs($user);
        $this->getJson('/api/user/purchases')->assertOk()->assertJsonPath('data.0.refund_request.status', 'refunded');
        $this->getJson('/api/user/refund-requests')->assertOk()->assertJsonPath('data.0.status', 'refunded');
        $this->assertEquals(5, $profile->fresh()->heart_inventory);
        $this->assertDatabaseCount('heart_transactions', 1);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/revenue/summary')->assertOk()->assertJsonPath('data.paid_purchases', 0)->assertJsonPath('data.refunded_purchases', 1);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertUnprocessable();
        $this->assertDatabaseCount('heart_transactions', 1);
    }

    public function test_unknown_provider_response_retries_with_the_same_idempotency_key(): void
    {
        [$admin, , $profile, , $request] = $this->order();
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['data' => ['id' => 'ref_confirmed', 'attributes' => ['status' => 'succeeded']]])]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertOk()->assertJsonPath('data.provider_status', 'unknown');
        $key = $request->fresh()->provider_request_key;
        $this->assertEquals(10, $profile->fresh()->heart_inventory);
        $this->postJson("/api/admin/refund-requests/{$request->id}/approve")->assertOk()->assertJsonPath('data.status', 'refunded');
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$sent]) {
            $this->assertSame($key, $sent->header('Idempotency-Key')[0]);
        }
    }

    public function test_rejection_is_visible_to_player_even_when_email_fails(): void
    {
        [$admin, $user, , , $request] = $this->order();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail offline'));
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$request->id}/reject", ['reason' => 'Hearts already used.'])
            ->assertOk()->assertJsonPath('data.email_delivery_status', 'failed');
        Sanctum::actingAs($user);
        $this->getJson('/api/user/purchases')->assertOk()->assertJsonPath('data.0.refund_request.status', 'rejected');
        $this->getJson('/api/user/refund-requests')->assertOk()->assertJsonPath('data.0.admin_notes', 'Hearts already used.');
    }
}
