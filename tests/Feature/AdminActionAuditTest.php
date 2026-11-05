<?php

namespace Tests\Feature;

use App\Models\AdminLog;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_lifecycle_records_actor_target_and_verified_ip_once_per_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Casey']);
        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '2001:db8::12'])
            ->withHeader('X-Forwarded-For', '203.0.113.99');
        $id = $this->postJson('/api/admin/levels', [
            'name' => 'Greetings', 'ip_address' => '203.0.113.55', 'admin_id' => 999,
        ])->assertCreated()->json('data.id');
        $url = '/api/admin/levels/'.$id;
        $this->putJson($url, ['name' => 'Daily greetings'])->assertOk();
        $this->postJson($url.'/archive')->assertOk();
        $this->postJson($url.'/restore')->assertOk();
        $this->deleteJson($url.'/force')->assertOk();
        $logs = AdminLog::orderBy('id')->get();
        $this->assertCount(5, $logs);
        foreach (['Created', 'Updated', 'Archived', 'Restored', 'Permanently deleted'] as $i => $verb) {
            $this->assertStringStartsWith($verb.' category #'.$id, $logs[$i]->action);
            $this->assertSame($admin->id, $logs[$i]->admin_id);
            $this->assertSame('Casey', $logs[$i]->admin_name);
            $this->assertSame('2001:db8::12', $logs[$i]->ip_address);
        }
        $admin->update(['name' => 'New name']);
        $this->getJson('/api/admin/logs')->assertOk()->assertJsonPath('data.0.admin_name', 'Casey')
            ->assertJsonPath('data.0.ip_address', '2001:db8::12');
        $this->postJson('/api/admin/levels', ['name' => ''])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/logs')->assertForbidden();
        $this->postJson('/api/admin/levels', ['name' => 'Denied'])->assertForbidden();
        $this->assertDatabaseCount('admin_logs', 5);
    }

    public function test_bulk_operations_only_record_affected_stories_and_resolve_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.1']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->withHeader('X-Forwarded-For', '198.51.100.7');
        $story = Story::create(['slug' => 'test', 'title' => 'Test', 'chapter_number' => 2]);
        $ids = ['ids' => [$story->id, $story->id, 99999]];
        $this->postJson('/api/admin/stories/bulk-archive', $ids)->assertOk();
        $this->postJson('/api/admin/stories/bulk-archive', $ids)->assertOk();
        $this->postJson('/api/admin/stories/bulk-restore', $ids)->assertOk();
        $this->postJson('/api/admin/stories/bulk-force-delete', $ids)->assertOk();
        $this->assertDatabaseCount('admin_logs', 3);
        $this->assertSame(3, AdminLog::where('ip_address', '198.51.100.7')->count());
        foreach (['Archived', 'Restored', 'Permanently deleted'] as $verb) {
            $this->assertDatabaseHas('admin_logs', ['action' => $verb.' story chapter #'.$story->id]);
        }
    }

    public function test_a_content_change_rolls_back_if_its_audit_entry_cannot_be_saved(): void
    {
        AdminLog::creating(function () {
            throw new \RuntimeException('Simulated audit storage failure');
        });
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson('/api/admin/levels', ['name' => 'Unrecorded change'])->assertServerError();
        $this->assertDatabaseMissing('levels', ['name' => 'Unrecorded change']);
        $this->assertDatabaseCount('admin_logs', 0);
    }

    public function test_historical_entries_only_show_an_ip_if_it_was_actually_recorded(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AdminLog::create(['admin_id' => $admin->id, 'action' => 'Created quiz']);
        AdminLog::create(['admin_id' => $admin->id, 'action' => 'Successful admin session login from IP 192.0.2.7']);
        $this->actingAs($admin)->getJson('/api/admin/logs')->assertOk()
            ->assertJsonPath('data.0.ip_address', '192.0.2.7')
            ->assertJsonPath('data.1.ip_address', null);
    }
}
