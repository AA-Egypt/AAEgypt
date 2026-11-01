<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\MeetingPolicy;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_gsr_of_group_a_attempting_to_update_group_b_meeting_receives_http_403_forbidden(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $gsrA = User::factory()->gsr($groupA->id)->create();
        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'title' => 'Original Title',
        ]);

        $response = $this->actingAs($gsrA)->putJson("/meetings/{$meetingB->id}", [
            'title' => 'Unauthorized Update Attempt',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('meetings', [
            'id' => $meetingB->id,
            'title' => 'Original Title',
        ]);
    }

    public function test_gsr_of_group_a_attempting_to_update_group_b_meeting_via_group_route_receives_http_403(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $gsrA = User::factory()->gsr($groupA->id)->create();
        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'title' => 'Group B Meeting',
        ]);

        $response = $this->actingAs($gsrA)->putJson("/groups/{$groupB->id}/meetings/{$meetingB->id}", [
            'title' => 'Group B New Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_web_admin_can_update_any_meeting(): void
    {
        $group = Group::factory()->create(['name_en' => 'Any Group']);
        $admin = User::factory()->webAdmin()->create();
        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Old Title',
        ]);

        $response = $this->actingAs($admin)->putJson("/meetings/{$meeting->id}", [
            'title' => 'Updated by Admin',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Updated by Admin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update',
            'resource' => 'Meeting',
            'resource_id' => (string) $meeting->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_gsr_can_update_own_group_meeting(): void
    {
        $group = Group::factory()->create(['name_en' => 'My Group']);
        $gsr = User::factory()->gsr($group->id)->create();
        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Initial Title',
        ]);

        $response = $this->actingAs($gsr)->putJson("/meetings/{$meeting->id}", [
            'title' => 'Updated by GSR',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Updated by GSR',
        ]);
    }

    public function test_delegate_can_update_own_group_meeting_and_is_forbidden_from_other_group(): void
    {
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();

        $delegate = User::factory()->delegate($groupA->id)->create();
        $meetingA = Meeting::factory()->create(['group_id' => $groupA->id, 'title' => 'Meeting A']);
        $meetingB = Meeting::factory()->create(['group_id' => $groupB->id, 'title' => 'Meeting B']);

        // Can update own group's meeting
        $responseA = $this->actingAs($delegate)->putJson("/meetings/{$meetingA->id}", [
            'title' => 'Updated Meeting A',
        ]);
        $responseA->assertStatus(200);

        // Cannot update other group's meeting
        $responseB = $this->actingAs($delegate)->putJson("/meetings/{$meetingB->id}", [
            'title' => 'Attempted Update Meeting B',
        ]);
        $responseB->assertStatus(403);
    }

    public function test_service_position_cannot_update_group_meetings(): void
    {
        $group = Group::factory()->create();
        $serviceUser = User::factory()->servicePosition()->create();
        $meeting = Meeting::factory()->create(['group_id' => $group->id]);

        $response = $this->actingAs($serviceUser)->putJson("/meetings/{$meeting->id}", [
            'title' => 'Service Position Update',
        ]);

        $response->assertStatus(403);
    }

    public function test_meeting_policy_methods_enforce_matrix_rules(): void
    {
        $policy = new MeetingPolicy;

        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();

        $admin = User::factory()->webAdmin()->make();
        $gsrA = User::factory()->gsr($groupA->id)->make();
        $delegateA = User::factory()->delegate($groupA->id)->make();
        $servicePos = User::factory()->servicePosition()->make();

        $meetingA = new Meeting(['group_id' => $groupA->id]);
        $meetingB = new Meeting(['group_id' => $groupB->id]);

        // viewAny: returns true for all authenticated service users
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($gsrA));
        $this->assertTrue($policy->viewAny($delegateA));
        $this->assertTrue($policy->viewAny($servicePos));

        // update: web_admin or own group for gsr/delegate
        $this->assertTrue($policy->update($admin, $meetingA));
        $this->assertTrue($policy->update($admin, $meetingB));
        $this->assertTrue($policy->update($gsrA, $meetingA));
        $this->assertFalse($policy->update($gsrA, $meetingB));
        $this->assertTrue($policy->update($delegateA, $meetingA));
        $this->assertFalse($policy->update($delegateA, $meetingB));
        $this->assertFalse($policy->update($servicePos, $meetingA));

        // delete: web_admin only
        $this->assertTrue($policy->delete($admin, $meetingA));
        $this->assertFalse($policy->delete($gsrA, $meetingA));
        $this->assertFalse($policy->delete($delegateA, $meetingA));
        $this->assertFalse($policy->delete($servicePos, $meetingA));
    }

    public function test_document_policy_methods_enforce_matrix_rules(): void
    {
        $policy = new DocumentPolicy;

        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();

        $admin = User::factory()->webAdmin()->make();
        $gsrA = User::factory()->gsr($groupA->id)->make();
        $delegateA = User::factory()->delegate($groupA->id)->make();
        $servicePos = User::factory()->servicePosition()->make();

        $docA = new Document(['group_id' => $groupA->id]);
        $docB = new Document(['group_id' => $groupB->id]);

        // viewAny: service roles allowed
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($gsrA));

        // view: own group or admin
        $this->assertTrue($policy->view($admin, $docA));
        $this->assertTrue($policy->view($gsrA, $docA));
        $this->assertFalse($policy->view($gsrA, $docB));
        $this->assertTrue($policy->view($delegateA, $docA));
        $this->assertFalse($policy->view($delegateA, $docB));
        $this->assertFalse($policy->view($servicePos, $docA));

        // update: own group or admin
        $this->assertTrue($policy->update($admin, $docB));
        $this->assertTrue($policy->update($gsrA, $docA));
        $this->assertFalse($policy->update($gsrA, $docB));

        // delete: admin only
        $this->assertTrue($policy->delete($admin, $docA));
        $this->assertFalse($policy->delete($gsrA, $docA));
    }

    public function test_ensure_group_access_middleware_enforces_route_group_model_binding(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group Alpha', 'slug' => 'group-alpha']);
        $groupB = Group::factory()->create(['name_en' => 'Group Beta', 'slug' => 'group-beta']);

        $gsrA = User::factory()->gsr($groupA->id)->create();
        $admin = User::factory()->webAdmin()->create();
        $serviceUser = User::factory()->servicePosition()->create();

        // GSR accessing own group -> 200
        $this->actingAs($gsrA)->getJson("/groups/{$groupA->id}")
            ->assertStatus(200);

        // GSR accessing other group -> 403
        $this->actingAs($gsrA)->getJson("/groups/{$groupB->id}")
            ->assertStatus(403);

        // Web Admin accessing other group -> 200 (global access)
        $this->actingAs($admin)->getJson("/groups/{$groupB->id}")
            ->assertStatus(200);

        // Service Position accessing group -> 403 (default DENY)
        $this->actingAs($serviceUser)->getJson("/groups/{$groupA->id}")
            ->assertStatus(403);
    }

    public function test_audit_logger_records_who_performed_the_action_and_changes_made(): void
    {
        $admin = User::factory()->webAdmin()->create();

        $payload = [
            'field' => 'schedule',
            'from' => '18:00',
            'to' => '19:00',
        ];

        $this->actingAs($admin);

        $log = AuditLogger::log(
            action: 'update_schedule',
            resource: 'Meeting',
            resourceId: 42,
            payload: $payload,
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $admin->id,
            'action' => 'update_schedule',
            'resource' => 'Meeting',
            'resource_id' => '42',
        ]);

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('update_schedule', $log->action);
        $this->assertSame('Meeting', $log->resource);
        $this->assertSame('42', $log->resource_id);
        $this->assertSame($payload, $log->payload);
        $this->assertNotNull($log->created_at);
    }

    public function test_admin_changes_to_access_matrix_are_logged_in_audit_table(): void
    {
        $admin = User::factory()->webAdmin()->create();
        $group = Group::factory()->create();
        $targetUser = User::factory()->create([
            'role' => 'gsr',
            'group_id' => null,
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/access-matrix', [
            'user_id' => $targetUser->id,
            'role' => 'delegate',
            'group_id' => $group->id,
        ]);

        $response->assertStatus(200);

        $targetUser->refresh();
        $this->assertSame('delegate', $targetUser->role);
        $this->assertSame($group->id, $targetUser->group_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'update_access_matrix',
            'resource' => 'User',
            'resource_id' => (string) $targetUser->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_modify_access_matrix(): void
    {
        $gsr = User::factory()->gsr(1)->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($gsr)->postJson('/admin/access-matrix', [
            'user_id' => $targetUser->id,
            'role' => 'web_admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_audit_logs_are_append_only_and_cannot_be_updated(): void
    {
        $log = AuditLog::create([
            'action' => 'initial_action',
            'resource' => 'Policy',
            'resource_id' => '1',
            'payload' => ['authorized' => true],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be updated.');

        $log->update(['action' => 'tampered_action']);
    }
}
