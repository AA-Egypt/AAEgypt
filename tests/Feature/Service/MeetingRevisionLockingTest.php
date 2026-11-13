<?php

namespace Tests\Feature\Service;

use App\Livewire\Service\MeetingEditor;
use App\Livewire\Service\MeetingRevisionHistory;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingRevision;
use App\Models\User;
use App\Services\Meeting\MeetingRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class MeetingRevisionLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_has_default_version_one(): void
    {
        $group = Group::factory()->create();
        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Original Zamalek Meeting',
        ]);

        $this->assertSame(1, $meeting->version);
    }

    public function test_saving_meeting_increments_version_and_records_revision(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Original Name',
            'start_time' => '19:00',
            'end_time' => '20:30',
            'version' => 1,
        ]);

        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Updated Name by GSR')
            ->set('start_time', '19:30')
            ->call('save')
            ->assertHasNoErrors();

        $meeting->refresh();

        $this->assertSame(2, $meeting->version);
        $this->assertSame('Updated Name by GSR', $meeting->name);
        $this->assertSame('19:30', $meeting->start_time);

        $this->assertDatabaseHas('meeting_revisions', [
            'meeting_id' => $meeting->id,
            'user_id' => $gsr->id,
            'version' => 2,
        ]);

        $revision = MeetingRevision::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($revision);
        $this->assertSame('Original Name', $revision->old_payload['name']);
        $this->assertSame('Updated Name by GSR', $revision->new_payload['name']);
        $this->assertSame('19:00', $revision->old_payload['start_time']);
        $this->assertSame('19:30', $revision->new_payload['start_time']);
    }

    public function test_concurrent_edit_conflict_halts_save_and_shows_diff(): void
    {
        $group = Group::factory()->create();
        $userA = User::factory()->gsr($group->id)->create([
            'name' => 'User A',
            'totp_secret' => 'TESTSECRET123456',
        ]);
        $userB = User::factory()->gsr($group->id)->create([
            'name' => 'User B',
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Initial Meeting Name',
            'start_time' => '19:00',
            'end_time' => '20:30',
            'notes' => 'Original notes',
            'version' => 3,
        ]);

        // User A and User B both opened the meeting when version was 3
        // User A saves first, updating start_time to 20:00 and bumping version to 4
        Livewire::actingAs($userA)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('form_version', 3)
            ->set('start_time', '20:00')
            ->call('save')
            ->assertHasNoErrors();

        $meeting->refresh();
        $this->assertSame(4, $meeting->version);
        $this->assertSame('20:00', $meeting->start_time);

        // User B attempts to save with stale form_version 3
        $componentB = Livewire::actingAs($userB)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('form_version', 3)
            ->set('name', 'User B Attempted Overwrite')
            ->set('notes', 'Conflicting notes from B')
            ->call('save');

        // Verify save was halted
        $meeting->refresh();
        $this->assertSame(4, $meeting->version);
        $this->assertSame('Initial Meeting Name', $meeting->name, 'User B overwrite must not be saved');
        $this->assertSame('20:00', $meeting->start_time, 'User A changes must be preserved');

        // Verify conflict state and diff highlights
        $componentB->assertHasErrors(['form_version']);
        $this->assertTrue($componentB->get('hasConflict'));
        $this->assertTrue($componentB->get('showConflictModal'));

        $diffs = $componentB->get('conflictDiffs');
        $this->assertNotEmpty($diffs);
        $this->assertArrayHasKey('start_time', $diffs);
        $this->assertSame('19:00', $diffs['start_time']['old_value']);
        $this->assertSame('20:00', $diffs['start_time']['new_value']);
        $this->assertSame('User A', $diffs['start_time']['changed_by']);
    }

    public function test_one_click_restore_reverts_meeting_data_and_increments_version(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Version 1 Name',
            'start_time' => '18:00',
            'end_time' => '19:30',
            'version' => 1,
        ]);

        // Save Edit 1 -> Version 2
        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Version 2 Name')
            ->set('start_time', '19:00')
            ->call('save');

        // Save Edit 2 -> Version 3
        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Version 3 Name')
            ->set('start_time', '20:00')
            ->call('save');

        $meeting->refresh();
        $this->assertSame(3, $meeting->version);
        $this->assertSame('Version 3 Name', $meeting->name);

        $v2Revision = MeetingRevision::where('meeting_id', $meeting->id)
            ->where('version', 2)
            ->firstOrFail();

        // 1-Click Restore to Version 2 via MeetingRevisionService
        $service = app(MeetingRevisionService::class);
        $restoredMeeting = $service->restore($v2Revision->id, $gsr->id);

        $this->assertSame(4, $restoredMeeting->version);
        $this->assertSame('Version 2 Name', $restoredMeeting->name);
        $this->assertSame('19:00', $restoredMeeting->start_time);

        // Verify that restoration is recorded in meeting_revisions
        $this->assertDatabaseHas('meeting_revisions', [
            'meeting_id' => $meeting->id,
            'version' => 4,
            'user_id' => $gsr->id,
        ]);
    }

    public function test_one_click_restore_via_meeting_revision_history_component(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Original Name',
            'version' => 1,
        ]);

        // Make an edit
        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'First Revision Title')
            ->call('save');

        // Make second edit
        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Second Revision Title')
            ->call('save');

        $firstRevision = MeetingRevision::where('meeting_id', $meeting->id)
            ->where('version', 2)
            ->firstOrFail();

        // Restore via MeetingRevisionHistory component
        Livewire::actingAs($gsr)
            ->test(MeetingRevisionHistory::class, ['meetingId' => $meeting->id])
            ->call('restore', $firstRevision->id)
            ->assertDispatched('meeting-restored')
            ->assertDispatched('meeting-updated');

        $meeting->refresh();
        $this->assertSame(4, $meeting->version);
        $this->assertSame('First Revision Title', $meeting->name);
    }

    public function test_one_click_restore_via_meeting_editor_component(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Initial Title',
            'version' => 1,
        ]);

        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Target Snapshot Title')
            ->call('save');

        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'Later Changed Title')
            ->call('save');

        $targetRevision = MeetingRevision::where('meeting_id', $meeting->id)
            ->where('version', 2)
            ->firstOrFail();

        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->call('restore', $targetRevision->id)
            ->assertDispatched('meeting-restored');

        $meeting->refresh();
        $this->assertSame(4, $meeting->version);
        $this->assertSame('Target Snapshot Title', $meeting->name);
    }

    public function test_gsr_cannot_restore_another_groups_meeting_revision(): void
    {
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();

        $gsrA = User::factory()->gsr($groupA->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meetingA = Meeting::factory()->create([
            'group_id' => $groupA->id,
            'name' => 'Group A Meeting',
            'version' => 1,
        ]);

        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'name' => 'Group B Meeting',
            'version' => 1,
        ]);

        $revisionB = MeetingRevision::factory()->create([
            'meeting_id' => $meetingB->id,
            'version' => 2,
            'new_payload' => ['name' => 'Group B Hacked Title'],
        ]);

        Livewire::actingAs($gsrA)
            ->test(MeetingRevisionHistory::class, ['meetingId' => $meetingA->id])
            ->call('restore', $revisionB->id)
            ->assertForbidden();

        Livewire::actingAs($gsrA)
            ->test(MeetingEditor::class, ['meetingId' => $meetingA->id])
            ->call('restore', $revisionB->id)
            ->assertForbidden();
    }

    public function test_web_admin_can_restore_any_meeting_revision(): void
    {
        $group = Group::factory()->create();
        $admin = User::factory()->webAdmin()->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Group Meeting',
            'version' => 1,
        ]);

        $revision = MeetingRevision::factory()->create([
            'meeting_id' => $meeting->id,
            'version' => 2,
            'new_payload' => ['name' => 'Admin Restored Title'],
        ]);

        Livewire::actingAs($admin)
            ->test(MeetingRevisionHistory::class, ['meetingId' => $meeting->id])
            ->call('restore', $revision->id)
            ->assertDispatched('meeting-restored');

        $meeting->refresh();
        $this->assertSame(2, $meeting->version);
        $this->assertSame('Admin Restored Title', $meeting->name);
    }

    public function test_meeting_revisions_table_is_append_only(): void
    {
        $revision = MeetingRevision::factory()->create([
            'version' => 2,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meeting revisions are append-only and cannot be updated.');

        $revision->update(['version' => 999]);
    }

    public function test_conflict_resolution_reload_and_overwrite_options(): void
    {
        $group = Group::factory()->create();
        $userA = User::factory()->gsr($group->id)->create(['name' => 'User A']);
        $userB = User::factory()->gsr($group->id)->create(['name' => 'User B']);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Base Meeting',
            'version' => 1,
        ]);

        // User A edits to 'User A Live Title'
        Livewire::actingAs($userA)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('name', 'User A Live Title')
            ->call('save');

        // User B has stale form with version 1
        $componentB = Livewire::actingAs($userB)
            ->test(MeetingEditor::class, ['meetingId' => $meeting->id])
            ->set('form_version', 1)
            ->set('name', 'User B Local Title')
            ->call('save');

        $this->assertTrue($componentB->get('hasConflict'));

        // Test Discard & Reload
        $componentB->call('resolveConflictReload');
        $this->assertSame('User A Live Title', $componentB->get('name'));
        $this->assertFalse($componentB->get('hasConflict'));

        // Test Overwrite
        $componentB->set('name', 'User B Forced Overwrite')
            ->call('resolveConflictOverwrite');

        $meeting->refresh();
        $this->assertSame('User B Forced Overwrite', $meeting->name);
        $this->assertSame(3, $meeting->version);
    }
}
