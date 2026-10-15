<?php

namespace Tests\Feature;

use App\Models\{User, PlayerProfile, Story, Purchase, PaymentTransaction, UserStoryUnlock, Level, Sign, Progress};
use App\Services\{AdminService, FinisherService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Mail};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_refund_with_no_hearts_revokes_only_the_story(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.paymongo.secret_key' => 'sk_test_example']);
        Http::fake(['*' => Http::response(['data' => ['id' => 'ref_story', 'attributes' => ['status' => 'succeeded']]])]);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $profile = PlayerProfile::create(['user_id' => $user->id, 'hearts' => 0, 'heart_inventory' => 0]);
        $story = Story::create(['title' => 'Paid chapter', 'slug' => 'paid-chapter', 'chapter_number' => 2, 'price' => 49, 'is_free' => false]);
        $purchase = Purchase::create(['user_id' => $user->id, 'product_type' => 'story', 'package_key' => 'story_chapter_2', 'story_id' => $story->id, 'quantity' => 1, 'amount' => 49, 'currency' => 'PHP', 'status' => 'paid', 'paid_at' => now()]);
        PaymentTransaction::create(['purchase_id' => $purchase->id, 'provider' => 'paymongo', 'provider_reference' => 'pay_story', 'status' => 'paid', 'amount' => 49, 'currency' => 'PHP', 'raw_payload' => ['data' => ['id' => 'pay_story']]]);
        UserStoryUnlock::create(['user_id' => $user->id, 'story_id' => $story->id, 'purchase_id' => $purchase->id, 'unlocked_at' => now()]);
        Sanctum::actingAs($user);
        $id = $this->postJson("/api/user/purchases/{$purchase->id}/refund-request", ['reason' => 'technical_issue'])->assertSuccessful()->json('data.id');
        $this->assertNotNull($id);
        // Even a balance acquired while the request is pending must not be deducted.
        $profile->update(['hearts' => 3, 'heart_inventory' => 7]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/refund-requests/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertEquals(3, $profile->fresh()->hearts);
        $this->assertEquals(7, $profile->fresh()->heart_inventory);
        $this->assertDatabaseCount('heart_transactions', 0);
        $this->assertDatabaseMissing('user_story_unlocks', ['user_id' => $user->id, 'story_id' => $story->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/user/purchases')->assertOk()->assertJsonPath('data.0.status', 'refunded');
        $this->postJson("/api/user/stories/{$story->id}/launch-ticket")->assertUnprocessable();
    }

    public function test_deactivation_revokes_existing_tokens_and_reactivation_does_not_restore_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('existing')->plainTextToken;
        app(AdminService::class)->deactivateUser($admin, $user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/auth/me')->assertUnauthorized();
        app(AdminService::class)->activateUser($admin, $user);
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_inactive_authenticated_users_and_admins_cannot_mutate_or_read_protected_data(): void
    {
        foreach (['user', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => false]);
            Sanctum::actingAs($user);
            $this->getJson('/api/auth/me')->assertForbidden();
            $this->putJson('/api/user/profile', ['name' => 'Changed'])->assertForbidden();
            $this->getJson('/api/admin/users')->assertForbidden();
            $this->assertNotSame('Changed', $user->fresh()->name);
        }
    }

    public function test_verification_mail_failure_returns_failure_and_removes_undelivered_code(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Transport unavailable'));
        $this->postJson('/api/auth/send-code', ['email' => 'learner@example.com'])->assertStatus(503)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('email_verification_codes', ['email' => 'learner@example.com']);
    }

    public function test_archived_completion_cannot_substitute_for_an_unfinished_active_sign(): void
    {
        $level = Level::create(['name' => 'Alphabet', 'order' => 1]);
        $a = Sign::create(['level_id' => $level->id, 'name' => 'A', 'fsl_name' => 'A']);
        $b = Sign::create(['level_id' => $level->id, 'name' => 'B', 'fsl_name' => 'B']);
        $user = User::factory()->create();
        PlayerProfile::create(['user_id' => $user->id]);
        Progress::create(['user_id' => $user->id, 'level_id' => $level->id, 'sign_id' => $a->id, 'is_completed' => true]);
        $a->delete();
        $service = app(FinisherService::class);
        $this->assertCount(0, $service->getFinishers($level->id));
        $this->assertCount(0, $service->getFinishers());
        Progress::create(['user_id' => $user->id, 'level_id' => $level->id, 'sign_id' => $b->id, 'is_completed' => true]);
        $this->assertCount(1, $service->getFinishers($level->id));
        $this->assertCount(1, $service->getFinishers());
        $a->restore();
        $this->assertCount(1, $service->getFinishers($level->id));
    }
    public function test_dashboard_query_count_does_not_grow_with_each_curriculum_level(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $level = Level::create(['name' => 'Alphabet', 'order' => 1]);
        $sign = Sign::create(['level_id' => $level->id, 'name' => 'A', 'fsl_name' => 'A']);
        Progress::create(['user_id' => $user->id, 'level_id' => $level->id, 'sign_id' => $sign->id, 'is_completed' => true]);
        $quiz = \App\Models\Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet quiz']);
        \App\Models\QuizAttempt::create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'score' => 1]);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $first = app(AdminService::class)->getAnalytics();
        $firstCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        $this->assertSame(2, $first['all_lessons'][0]['activity_count']);
        $this->assertSame(100, $first['all_lessons'][0]['completion']);
        $this->assertEquals(2, array_sum(array_column($first['weekly_activity'], 'count')));
        for ($n = 2; $n <= 12; $n++) {
            Level::create(['name' => 'Category '.$n, 'order' => $n]);
        }
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $expanded = app(AdminService::class)->getAnalytics();
        $expandedCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertCount(12, $expanded['all_lessons']);
        $this->assertSame($firstCount, $expandedCount);
        $this->assertLessThan(35, $expandedCount);
    }

    public function test_bounded_reconciliation_rotates_past_pending_refunds(): void
    {
        Http::preventStrayRequests();
        config(['services.paymongo.secret_key' => 'sk_test_example']);
        $user = User::factory()->create();
        $ids = [];
        for ($n = 1; $n <= 3; $n++) {
            $purchase = Purchase::create(['user_id' => $user->id, 'product_type' => 'hearts', 'package_key' => 'small_hearts', 'quantity' => 5, 'amount' => 49, 'currency' => 'PHP', 'status' => 'paid']);
            $refund = \App\Models\RefundRequest::create(['user_id' => $user->id, 'purchase_id' => $purchase->id, 'reason' => 'other', 'amount' => 49, 'currency' => 'PHP', 'status' => 'approved', 'provider_status' => 'pending', 'paymongo_refund_id' => 'ref_'.$n]);
            $ids[] = $refund->paymongo_refund_id;
        }
        Http::fake(fn ($request) => Http::response(['data' => ['id' => basename($request->url()), 'attributes' => ['status' => 'pending']]]));
        $service = app(\App\Services\RefundService::class);
        for ($n = 0; $n < 3; $n++) {
            $service->syncProcessingRefunds($user->id, 1, 2);
        }
        Http::assertSentCount(3);
        $visited = [];
        foreach (Http::recorded() as [$request]) {
            $visited[] = basename($request->url());
        }
        $this->assertSame($ids, $visited);
    }

}
