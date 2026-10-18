<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryArchiveRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_an_archived_title_allocates_a_slug_without_preventing_restore(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $story = Story::create([
            'slug' => 'review-chapter', 'title' => 'Review Chapter', 'chapter_number' => 2,
        ]);
        Story::create([
            'slug' => 'review-chapter-1', 'title' => 'Review Chapter', 'chapter_number' => 3,
        ]);

        $this->actingAs($admin)->postJson("/api/admin/stories/{$story->id}/archive")->assertOk();
        $this->postJson('/api/admin/stories', ['title' => 'Review Chapter', 'chapter_number' => 4])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'review-chapter-2');
        $this->postJson("/api/admin/stories/{$story->id}/restore")->assertOk();
        $this->assertDatabaseCount('stories', 3);
        $this->assertNotSoftDeleted('stories', ['id' => $story->id]);
    }

    public function test_automatic_chapter_number_does_not_reuse_an_archived_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Story::create(['slug' => 'arrival', 'title' => 'Arrival', 'chapter_number' => 1]);
        $story = Story::create(['slug' => 'latest', 'title' => 'Latest', 'chapter_number' => 7]);

        $this->actingAs($admin)->postJson("/api/admin/stories/{$story->id}/archive")->assertOk();
        $this->postJson('/api/admin/stories', ['title' => 'Next Chapter'])
            ->assertCreated()
            ->assertJsonPath('data.chapter_number', 8)
            ->assertJsonPath('data.order', 8);
    }

    public function test_paid_webhook_preserves_archived_story_ownership_and_is_idempotent(): void
    {
        config()->set('services.paymongo.mode', 'test');
        config()->set('services.paymongo.webhook_secret', 'whsec_archive_test');
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create(['role' => 'user']);
        $story = Story::create([
            'slug' => 'paid-chapter', 'title' => 'Paid Chapter', 'chapter_number' => 2,
            'is_free' => false, 'price' => 49,
        ]);
        $purchase = Purchase::create([
            'user_id' => $player->id, 'product_type' => 'story', 'story_id' => $story->id,
            'package_key' => 'story_chapter_2', 'quantity' => 1, 'amount' => 49,
            'currency' => 'PHP', 'status' => 'pending', 'provider' => 'paymongo',
            'checkout_session_id' => 'cs_archived_story',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/stories/{$story->id}/archive")->assertOk();
        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => [
                'id' => 'cs_archived_story',
                'attributes' => ['reference_number' => 'purchase:'.$purchase->id],
            ],
        ]]], JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_archive_test');
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',te='.$signature.',li=',
        ];

        // A retry while archived must not duplicate the payment or entitlement.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $payload)->assertOk();
        }
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'paid']);
        $this->assertDatabaseHas('user_story_unlocks', [
            'user_id' => $player->id, 'story_id' => $story->id, 'purchase_id' => $purchase->id,
        ]);

        // Ownership must not expose archived content or allow a new checkout/launch.
        $this->actingAs($player)->getJson('/api/stories')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/stories/{$story->id}")->assertNotFound();
        $this->postJson("/api/user/stories/{$story->id}/checkout")->assertNotFound();
        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")->assertNotFound();

        $this->actingAs($admin)->postJson("/api/admin/stories/{$story->id}/restore")->assertOk();
        $this->call('POST', '/api/payments/webhook', [], [], [], $headers, $payload)->assertOk();
        $this->assertDatabaseCount('user_story_unlocks', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('heart_transactions', 0);
        $this->actingAs($player)->getJson('/api/stories')
            ->assertOk()->assertJsonPath('data.0.is_unlocked', true);
        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")
            ->assertOk()->assertJsonPath('data.story_id', $story->id);
    }
}
