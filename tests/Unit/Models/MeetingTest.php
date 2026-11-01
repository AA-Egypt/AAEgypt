<?php

namespace Tests\Unit\Models;

use App\Http\Requests\MeetingFormRequest;
use App\Models\Group;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test meeting creation with all required fields and relation to Group.
     */
    public function test_meeting_can_be_created_with_all_attributes_and_belongs_to_group(): void
    {
        $group = Group::factory()->create([
            'name_en' => 'Zamalek Serenity Group',
            'name_ar' => 'مجموعة الزمالك للسكينة',
        ]);

        $meeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Thursday Evening Discussion',
            'day_of_week' => 4, // Thursday
            'start_time' => '19:30',
            'end_time' => '21:00',
            'venue_address' => '15 Brazil Street, Zamalek',
            'city' => 'Cairo',
            'latitude' => 30.0626300,
            'longitude' => 31.2223400,
            'type' => 'hybrid',
            'online_link' => 'https://zoom.us/j/123456789',
            'language' => 'bilingual',
            'notes' => 'Open meeting with Arabic and English translation available.',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'group_id' => $group->id,
            'name' => 'Thursday Evening Discussion',
            'day_of_week' => 4,
            'start_time' => '19:30',
            'end_time' => '21:00',
            'venue_address' => '15 Brazil Street, Zamalek',
            'city' => 'Cairo',
            'type' => 'hybrid',
            'language' => 'bilingual',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Group::class, $meeting->group);
        $this->assertEquals($group->id, $meeting->group->id);
        $this->assertEquals('15 Brazil Street, Zamalek', $meeting->venue_address);
        $this->assertEquals(30.06263, round($meeting->latitude, 5));
        $this->assertEquals(31.22234, round($meeting->longitude, 5));
    }

    /**
     * Test getStartTimeFormattedAttribute converts H:i (24h) to formatted 12-hour time.
     */
    public function test_start_time_formatted_accessor(): void
    {
        $meeting1 = new Meeting(['start_time' => '19:00']);
        $this->assertSame('7:00 PM', $meeting1->start_time_formatted);

        $meeting2 = new Meeting(['start_time' => '09:30']);
        $this->assertSame('9:30 AM', $meeting2->start_time_formatted);

        $meeting3 = new Meeting(['start_time' => '00:00']);
        $this->assertSame('12:00 AM', $meeting3->start_time_formatted);

        $meeting4 = new Meeting(['start_time' => null]);
        $this->assertNull($meeting4->start_time_formatted);
    }

    /**
     * Test getIsTodayAttribute evaluates strictly in Africa/Cairo timezone.
     */
    public function test_is_today_accessor_evaluates_strictly_in_africa_cairo(): void
    {
        // 2026-11-01 is Sunday (dayOfWeek 0)
        // At 23:00 UTC on Sunday, Cairo time is 01:00 on Monday (UTC+2 in November)
        Carbon::setTestNow(Carbon::parse('2026-11-01 23:00:00', 'UTC'));

        $cairoNow = Carbon::now('Africa/Cairo');
        $this->assertSame(1, $cairoNow->dayOfWeek); // Cairo is Monday

        $sundayMeeting = new Meeting(['day_of_week' => 0]); // Sunday
        $mondayMeeting = new Meeting(['day_of_week' => 1]); // Monday

        // In Cairo, it is already Monday, NOT Sunday
        $this->assertFalse($sundayMeeting->is_today);
        $this->assertTrue($mondayMeeting->is_today);
    }

    /**
     * Test getIsLiveNowAttribute when meeting is active, today, and currently in session in Cairo.
     */
    public function test_is_live_now_accessor(): void
    {
        // Set Cairo time to Monday at 19:30
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:30:00', 'Africa/Cairo'));

        $meeting = new Meeting([
            'day_of_week' => 1, // Monday
            'start_time' => '19:00',
            'end_time' => '20:30',
            'is_active' => true,
        ]);

        $this->assertTrue($meeting->is_today);
        $this->assertTrue($meeting->is_live_now);

        // Before start time
        Carbon::setTestNow(Carbon::parse('2026-11-02 18:45:00', 'Africa/Cairo'));
        $this->assertFalse($meeting->is_live_now);

        // After end time
        Carbon::setTestNow(Carbon::parse('2026-11-02 20:45:00', 'Africa/Cairo'));
        $this->assertFalse($meeting->is_live_now);

        // Inactive meeting should never be live now
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:30:00', 'Africa/Cairo'));
        $meeting->is_active = false;
        $this->assertFalse($meeting->is_live_now);

        // Different day of week should not be live now
        $meeting->is_active = true;
        $meeting->day_of_week = 2; // Tuesday
        $this->assertFalse($meeting->is_live_now);
    }

    /**
     * Test daylight saving time offset handling in Africa/Cairo (UTC+3 in summer, UTC+2 in winter).
     */
    public function test_daylight_saving_offset_handled_correctly_in_africa_cairo(): void
    {
        $meeting = new Meeting([
            'day_of_week' => 3, // Wednesday
            'start_time' => '19:00',
            'end_time' => '20:30',
            'is_active' => true,
        ]);

        // Summer: July 15, 2026 (DST active: UTC+3)
        $summerDate = Carbon::parse('2026-07-15 12:00:00', 'Africa/Cairo');
        $this->assertTrue($meeting->isCairoDaylightSaving($summerDate));
        $this->assertSame(3, $meeting->getCairoOffsetHours($summerDate));

        // Winter: January 15, 2026 (DST inactive: UTC+2)
        $winterDate = Carbon::parse('2026-01-15 12:00:00', 'Africa/Cairo');
        $this->assertFalse($meeting->isCairoDaylightSaving($winterDate));
        $this->assertSame(2, $meeting->getCairoOffsetHours($winterDate));

        // When current time in UTC is 16:30 on Wednesday:
        // In July (UTC+3), 16:30 UTC = 19:30 Cairo (meeting is live between 19:00 and 20:30)
        Carbon::setTestNow(Carbon::parse('2026-07-15 16:30:00', 'UTC'));
        $this->assertTrue($meeting->is_live_now);

        // In January (UTC+2), 16:30 UTC = 18:30 Cairo (meeting is not live yet; starts at 19:00)
        Carbon::setTestNow(Carbon::parse('2026-01-14 16:30:00', 'UTC')); // 2026-01-14 is Wednesday
        $this->assertFalse($meeting->is_live_now);
    }

    /**
     * Test query scopes: scopeActive, scopeToday, scopeByCity.
     */
    public function test_query_scopes(): void
    {
        // Fix test time to Monday in Africa/Cairo
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Africa/Cairo'));
        $group = Group::factory()->create();

        $activeCairoMonday = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Active Cairo Monday Meeting',
            'city' => 'Cairo',
            'day_of_week' => 1, // Monday
            'is_active' => true,
        ]);

        $inactiveCairoMonday = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Inactive Cairo Monday Meeting',
            'city' => 'Cairo',
            'day_of_week' => 1, // Monday
            'is_active' => false,
        ]);

        $activeAlexMonday = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Active Alex Monday Meeting',
            'city' => 'Alexandria',
            'day_of_week' => 1, // Monday
            'is_active' => true,
        ]);

        $activeCairoTuesday = Meeting::factory()->create([
            'group_id' => $group->id,
            'name' => 'Active Cairo Tuesday Meeting',
            'city' => 'Cairo',
            'day_of_week' => 2, // Tuesday
            'is_active' => true,
        ]);

        // 1. scopeActive
        $activeMeetings = Meeting::active()->pluck('id');
        $this->assertContains($activeCairoMonday->id, $activeMeetings);
        $this->assertContains($activeAlexMonday->id, $activeMeetings);
        $this->assertContains($activeCairoTuesday->id, $activeMeetings);
        $this->assertNotContains($inactiveCairoMonday->id, $activeMeetings);

        // 2. scopeToday (Monday)
        $todayMeetings = Meeting::today()->pluck('id');
        $this->assertContains($activeCairoMonday->id, $todayMeetings);
        $this->assertContains($inactiveCairoMonday->id, $todayMeetings);
        $this->assertContains($activeAlexMonday->id, $todayMeetings);
        $this->assertNotContains($activeCairoTuesday->id, $todayMeetings);

        // 3. scopeByCity
        $cairoMeetings = Meeting::byCity('Cairo')->pluck('id');
        $this->assertContains($activeCairoMonday->id, $cairoMeetings);
        $this->assertContains($inactiveCairoMonday->id, $cairoMeetings);
        $this->assertContains($activeCairoTuesday->id, $cairoMeetings);
        $this->assertNotContains($activeAlexMonday->id, $cairoMeetings);

        // Combined scopes
        $activeCairoToday = Meeting::active()->today()->byCity('Cairo')->get();
        $this->assertCount(1, $activeCairoToday);
        $this->assertEquals($activeCairoMonday->id, $activeCairoToday->first()->id);
    }

    /**
     * Test MeetingFormRequest validation succeeds with valid Egypt coordinates and sequencing.
     */
    public function test_form_request_passes_with_valid_data(): void
    {
        $group = Group::factory()->create();

        $data = [
            'group_id' => $group->id,
            'name' => 'Maadi Group Meeting',
            'day_of_week' => 2,
            'start_time' => '19:00',
            'end_time' => '20:30',
            'venue_address' => 'Road 9, Maadi',
            'city' => 'Cairo',
            'latitude' => 29.9599800,
            'longitude' => 31.2599700,
            'type' => 'in_person',
            'online_link' => null,
            'language' => 'ar',
            'notes' => 'Beginner meeting',
            'is_active' => true,
        ];

        $request = new MeetingFormRequest;
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test Acceptance Criterion: End time earlier than start time fails validation.
     */
    public function test_form_request_fails_when_end_time_earlier_than_start_time(): void
    {
        $group = Group::factory()->create();

        $data = [
            'group_id' => $group->id,
            'name' => 'Invalid Time Meeting',
            'day_of_week' => 0,
            'start_time' => '19:00',
            'end_time' => '18:00', // Earlier than start_time
            'venue_address' => 'Some address',
            'city' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'type' => 'in_person',
            'language' => 'ar',
        ];

        $request = MeetingFormRequest::create('/meetings', 'POST', $data);
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('end_time'));
    }

    /**
     * Test Acceptance Criterion: End time equal to start time fails validation.
     */
    public function test_form_request_fails_when_end_time_equals_start_time(): void
    {
        $group = Group::factory()->create();

        $data = [
            'group_id' => $group->id,
            'name' => 'Equal Time Meeting',
            'day_of_week' => 0,
            'start_time' => '19:00',
            'end_time' => '19:00', // Equal to start_time
            'venue_address' => 'Some address',
            'city' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'type' => 'in_person',
            'language' => 'ar',
        ];

        $request = MeetingFormRequest::create('/meetings', 'POST', $data);
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('end_time'));
    }

    /**
     * Test Acceptance Criterion: Coordinates outside Egypt fail validation (Lat 21.5 to 31.8, Lon 24.5 to 37.0).
     */
    public function test_form_request_fails_when_coordinates_outside_egypt(): void
    {
        $group = Group::factory()->create();

        $baseData = [
            'group_id' => $group->id,
            'name' => 'Out of Bounds Meeting',
            'day_of_week' => 0,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'venue_address' => 'Somewhere',
            'city' => 'Cairo',
            'type' => 'in_person',
            'language' => 'ar',
        ];

        // 1. Latitude too low (south of Egypt, < 21.5)
        $data1 = array_merge($baseData, ['latitude' => 21.4, 'longitude' => 31.0]);
        $req1 = MeetingFormRequest::create('/meetings', 'POST', $data1);
        $v1 = Validator::make($data1, $req1->rules());
        $req1->withValidator($v1);
        $this->assertTrue($v1->fails());
        $this->assertTrue($v1->errors()->has('latitude'));

        // 2. Latitude too high (north in Mediterranean Sea, > 31.8)
        $data2 = array_merge($baseData, ['latitude' => 31.9, 'longitude' => 31.0]);
        $req2 = MeetingFormRequest::create('/meetings', 'POST', $data2);
        $v2 = Validator::make($data2, $req2->rules());
        $req2->withValidator($v2);
        $this->assertTrue($v2->fails());
        $this->assertTrue($v2->errors()->has('latitude'));

        // 3. Longitude too low (west in Libya, < 24.5)
        $data3 = array_merge($baseData, ['latitude' => 28.0, 'longitude' => 24.4]);
        $req3 = MeetingFormRequest::create('/meetings', 'POST', $data3);
        $v3 = Validator::make($data3, $req3->rules());
        $req3->withValidator($v3);
        $this->assertTrue($v3->fails());
        $this->assertTrue($v3->errors()->has('longitude'));

        // 4. Longitude too high (east in Red Sea / Saudi, > 37.0)
        $data4 = array_merge($baseData, ['latitude' => 28.0, 'longitude' => 37.1]);
        $req4 = MeetingFormRequest::create('/meetings', 'POST', $data4);
        $v4 = Validator::make($data4, $req4->rules());
        $req4->withValidator($v4);
        $this->assertTrue($v4->fails());
        $this->assertTrue($v4->errors()->has('longitude'));
    }

    /**
     * Test coordinates exactly on Egypt bounding box boundaries pass validation.
     */
    public function test_form_request_passes_on_egypt_bounding_box_boundaries(): void
    {
        $group = Group::factory()->create();

        $data = [
            'group_id' => $group->id,
            'name' => 'Boundary Meeting',
            'day_of_week' => 5,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'venue_address' => 'Border Post',
            'city' => 'Aswan',
            'latitude' => 21.5, // Min boundary
            'longitude' => 37.0, // Max boundary
            'type' => 'in_person',
            'language' => 'ar',
        ];

        $request = MeetingFormRequest::create('/meetings', 'POST', $data);
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());

        // Test opposite boundaries
        $data['latitude'] = 31.8; // Max boundary
        $data['longitude'] = 24.5; // Min boundary
        $request2 = MeetingFormRequest::create('/meetings', 'POST', $data);
        $validator2 = Validator::make($data, $request2->rules());
        $request2->withValidator($validator2);

        $this->assertTrue($validator2->passes());
    }
}

