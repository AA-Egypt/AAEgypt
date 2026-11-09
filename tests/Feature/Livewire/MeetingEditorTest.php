<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Service\MeetingEditor;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_meeting_editor_and_is_redirected_to_login(): void
    {
        $response = $this->get('/service/meetings');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_totp_is_redirected_to_mfa_setup(): void
    {
        $group = Group::factory()->create();
        $user = User::factory()->gsr($group->id)->create([
            'totp_secret' => null,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/service/meetings');

        $response->assertRedirect(route('mfa.setup'));
    }

    public function test_authenticated_user_with_unverified_mfa_session_is_redirected_to_mfa_challenge(): void
    {
        $group = Group::factory()->create();
        $user = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/service/meetings');

        $response->assertRedirect(route('mfa.challenge'));
    }

    public function test_authenticated_gsr_with_mfa_can_access_meeting_editor(): void
    {
        $group = Group::factory()->create(['name_en' => 'Zamalek Group']);
        $user = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
            'must_change_password' => false,
        ]);
        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Zamalek Serenity Meeting',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['mfa_verified' => true])
            ->get('/service/meetings');

        $response->assertStatus(200);
        $response->assertSeeLivewire(MeetingEditor::class);
        $response->assertSee('Zamalek Serenity Meeting');
    }

    public function test_gsr_sees_only_own_group_meetings(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $gsrA = User::factory()->gsr($groupA->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meetingA = Meeting::factory()->create([
            'group_id' => $groupA->id,
            'name' => 'Group A Unique Meeting',
        ]);

        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'name' => 'Group B Hidden Meeting',
        ]);

        $component = Livewire::actingAs($gsrA)
            ->test(MeetingEditor::class);

        $component->assertSee('Group A Unique Meeting');
        $component->assertDontSee('Group B Hidden Meeting');

        $loadedMeetings = $component->get('meetings');
        $this->assertTrue($loadedMeetings->contains('id', $meetingA->id));
        $this->assertFalse($loadedMeetings->contains('id', $meetingB->id));
    }

    public function test_gsr_cannot_select_another_groups_meeting(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $gsrA = User::factory()->gsr($groupA->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'name' => 'Group B Meeting',
        ]);

        Livewire::actingAs($gsrA)
            ->test(MeetingEditor::class)
            ->call('selectMeeting', $meetingB->id)
            ->assertForbidden();
    }

    public function test_gsr_cannot_update_another_groups_meeting(): void
    {
        $groupA = Group::factory()->create(['name_en' => 'Group A']);
        $groupB = Group::factory()->create(['name_en' => 'Group B']);

        $gsrA = User::factory()->gsr($groupA->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meetingB = Meeting::factory()->create([
            'group_id' => $groupB->id,
            'name' => 'Original Group B Title',
        ]);

        $component = Livewire::actingAs($gsrA)
            ->test(MeetingEditor::class);

        $component->set('meetingId', $meetingB->id);
        $component->set('name', 'Tampered Name');
        $component->call('save')
            ->assertForbidden();

        $this->assertDatabaseHas('meetings', [
            'id' => $meetingB->id,
            'name' => 'Original Group B Title',
        ]);
    }

    public function test_delegate_can_view_and_edit_own_group_meetings(): void
    {
        $group = Group::factory()->create();
        $delegate = User::factory()->delegate($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Delegate Group Meeting',
            'venue_address' => 'Old Address',
            'start_time' => '19:00',
            'end_time' => '20:30',
        ]);

        Livewire::actingAs($delegate)
            ->test(MeetingEditor::class)
            ->set('name', 'Delegate Updated Meeting')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Meeting updated and live on website.');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'name' => 'Delegate Updated Meeting',
        ]);
    }

    public function test_user_without_assigned_group_is_forbidden(): void
    {
        $userWithoutGroup = User::factory()->gsr(null)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->actingAs($userWithoutGroup)
            ->withSession(['mfa_verified' => true])
            ->get('/service/meetings');

        $response->assertStatus(403);
    }

    public function test_unauthorized_service_position_cannot_access_meeting_editor(): void
    {
        $servicePositionUser = User::factory()->servicePosition()->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->actingAs($servicePositionUser)
            ->withSession(['mfa_verified' => true])
            ->get('/service/meetings');

        $response->assertStatus(403);
    }

    public function test_successful_edit_immediately_reflects_on_public_meetings_page(): void
    {
        $group = Group::factory()->create(['name_en' => 'Maadi Fellowship']);
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Old Maadi Meeting Name',
            'venue_address' => '10 Old Road',
            'city' => 'Cairo',
            'start_time' => '19:00',
            'end_time' => '20:30',
            'day_of_week' => 2,
            'type' => 'in_person',
            'language' => 'en',
            'is_active' => true,
        ]);

        // Ensure old name is currently visible on public page
        $publicBefore = $this->get('/ar/meetings');
        $publicBefore->assertStatus(200);
        $publicBefore->assertSee('Old Maadi Meeting Name');

        // Update via Livewire component
        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class)
            ->set('name', 'Brand New Maadi Meeting')
            ->set('address', '45 New Street, Maadi')
            ->set('start_time', '20:00')
            ->set('end_time', '21:30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Meeting updated and live on website.');

        // Verify database persistence
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'name' => 'Brand New Maadi Meeting',
            'venue_address' => '45 New Street, Maadi',
            'start_time' => '20:00',
            'end_time' => '21:30',
        ]);

        // Verify immediate live reflection on public /meetings page
        $publicAfter = $this->get('/ar/meetings');
        $publicAfter->assertStatus(200);
        $publicAfter->assertSee('Brand New Maadi Meeting');
        $publicAfter->assertDontSee('Old Maadi Meeting Name');
    }

    public function test_pin_confirmation_updates_coordinates(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Pin Test Meeting',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ]);

        $component = Livewire::actingAs($gsr)
            ->test(MeetingEditor::class);

        // Simulate pin confirmation / click / drag
        $component->call('confirmPin', 30.1234567, 31.6543210);

        $component->assertSet('latitude', 30.1234567);
        $component->assertSet('longitude', 31.6543210);
        $component->assertSet('coordinates', '30.1234567, 31.654321');

        $component->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Meeting updated and live on website.');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'latitude' => 30.1234567,
            'longitude' => 31.6543210,
        ]);
    }

    public function test_validation_prevents_saving_invalid_data(): void
    {
        $group = Group::factory()->create();
        $gsr = User::factory()->gsr($group->id)->create([
            'totp_secret' => 'TESTSECRET123456',
        ]);

        Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Valid Initial Name',
        ]);

        Livewire::actingAs($gsr)
            ->test(MeetingEditor::class)
            ->set('name', '')
            ->set('address', '')
            ->call('save')
            ->assertHasErrors(['name', 'address']);
    }
}
