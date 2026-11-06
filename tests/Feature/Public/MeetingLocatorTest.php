<?php

namespace Tests\Feature\Public;

use App\Models\Group;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MeetingLocatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test public meetings page renders with client-side locator elements and zero cookies.
     */
    public function test_meetings_page_renders_with_locator_button_and_privacy_elements(): void
    {
        $responseEn = $this->get('/en/meetings');
        $responseEn->assertOk();
        $responseEn->assertSee('id="find-near-me-btn"', false);
        $responseEn->assertSee('Find Meetings Near Me');
        $responseEn->assertSee('id="city-filter"', false);
        $responseEn->assertSee('id="meetings-data"', false);
        $responseEn->assertSee('type="application/json"', false);
        $responseEn->assertSee('id="meeting-cards-container"', false);
        $responseEn->assertHeaderMissing('Set-Cookie');

        $responseAr = $this->get('/ar/meetings');
        $responseAr->assertOk();
        $responseAr->assertSee('id="find-near-me-btn"', false);
        $responseAr->assertSee('البحث عن الاجتماعات القريبة مني');
        $responseAr->assertSee('id="city-filter"', false);
        $responseAr->assertSee('id="meetings-data"', false);
        $responseAr->assertHeaderMissing('Set-Cookie');
    }

    /**
     * Test meeting coordinate list rendered into a JSON script tag in the HTML.
     */
    public function test_meetings_json_script_tag_contains_coordinates_and_today_flag(): void
    {
        $group = Group::create([
            'name_en' => 'Cairo Group',
            'name_ar' => 'مجموعة القاهرة',
            'slug' => 'cairo-group',
        ]);

        $cairoNow = Carbon::now(Meeting::TIMEZONE);
        $todayDay = $cairoNow->dayOfWeek;
        $otherDay = ($todayDay + 3) % 7;

        $todayMeeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Downtown Recovery Meeting',
            'day_of_week' => $todayDay,
            'start_time' => '18:00',
            'end_time' => '19:30',
            'city' => 'Cairo',
            'venue_address' => '12 Tahrir Square',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'type' => 'in_person',
            'is_active' => true,
        ]);

        $futureMeeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Alexandria Sea Breeze',
            'day_of_week' => $otherDay,
            'start_time' => '20:00',
            'end_time' => '21:30',
            'city' => 'Alexandria',
            'venue_address' => 'Corniche Road',
            'latitude' => 31.2001,
            'longitude' => 29.9187,
            'type' => 'in_person',
            'is_active' => true,
        ]);

        $response = $this->get('/en/meetings');
        $response->assertOk();

        // Extract JSON content from <script id="meetings-data" type="application/json">
        $html = $response->getContent();
        $matched = preg_match('/<script id="meetings-data"[^>]*>(.*?)<\/script>/s', $html, $matches);
        $this->assertSame(1, $matched, 'Script tag #meetings-data not found in HTML response');

        $jsonData = json_decode(trim($matches[1]), true);
        $this->assertIsArray($jsonData);
        $this->assertCount(2, $jsonData);

        $todayData = collect($jsonData)->firstWhere('id', $todayMeeting->id);
        $futureData = collect($jsonData)->firstWhere('id', $futureMeeting->id);

        $this->assertNotNull($todayData);
        $this->assertSame(30.0444, $todayData['latitude']);
        $this->assertSame(31.2357, $todayData['longitude']);
        $this->assertTrue($todayData['is_today']);
        $this->assertSame('Cairo', $todayData['city']);

        $this->assertNotNull($futureData);
        $this->assertSame(31.2001, $futureData['latitude']);
        $this->assertSame(29.9187, $futureData['longitude']);
        $this->assertFalse($futureData['is_today']);
        $this->assertSame('Alexandria', $futureData['city']);
    }

    /**
     * Test responsive card view displays group name, day, start/end time, meeting type badge,
     * venue address, and directions buttons for Google Maps and Apple Maps.
     */
    public function test_meeting_card_renders_all_required_information_and_map_direction_links(): void
    {
        $group = Group::create([
            'name_en' => 'Maadi Serenity Group',
            'name_ar' => 'مجموعة المعادي للسكينة',
            'slug' => 'maadi-serenity-group',
        ]);

        $meeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Maadi Step Study',
            'day_of_week' => 1,
            'start_time' => '19:00',
            'end_time' => '20:30',
            'venue_address' => 'Road 9, Maadi',
            'city' => 'Cairo',
            'latitude' => 29.9602,
            'longitude' => 31.2569,
            'type' => 'in_person',
            'is_active' => true,
        ]);

        $response = $this->get('/en/meetings');
        $response->assertOk();

        // Group name and meeting title
        $response->assertSee('Maadi Serenity Group');
        $response->assertSee('Maadi Step Study');

        // Day of week and times
        $response->assertSee('Monday');
        $response->assertSee('19:00');
        $response->assertSee('20:30');

        // Venue address
        $response->assertSee('Road 9, Maadi');
        $response->assertSee('Cairo');

        // Meeting type badge
        $response->assertSee('In-Person');

        // Direction buttons: Google Maps and Apple Maps
        $expectedGoogleMapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=29.9602,31.2569';
        $expectedAppleMapsUrl = 'https://maps.apple.com/?daddr=29.9602,31.2569';

        $response->assertSee($expectedGoogleMapsUrl, false);
        $response->assertSee($expectedAppleMapsUrl, false);
        $response->assertSee('Open in Google Maps');
        $response->assertSee('Open in Apple Maps');

        // Client-side distance badge container
        $response->assertSee('data-distance-badge', false);
    }

    /**
     * Test manual dropdown works as fallback when GPS is denied or unavailable.
     */
    public function test_manual_city_dropdown_works_as_fallback_with_available_cities(): void
    {
        $group = Group::create([
            'name_en' => 'Fellowship Group',
            'name_ar' => 'مجموعة الزمالة',
            'slug' => 'fellowship-group',
        ]);

        Meeting::create([
            'group_id' => $group->id,
            'name' => 'Zamalek Discussion',
            'city' => 'Cairo',
            'latitude' => 30.0618,
            'longitude' => 31.2194,
            'is_active' => true,
        ]);

        Meeting::create([
            'group_id' => $group->id,
            'name' => 'Smouha Group',
            'city' => 'Alexandria',
            'latitude' => 31.2156,
            'longitude' => 29.9553,
            'is_active' => true,
        ]);

        Meeting::create([
            'group_id' => $group->id,
            'name' => 'Dokki Steps',
            'city' => 'Giza',
            'latitude' => 30.0384,
            'longitude' => 31.2124,
            'is_active' => true,
        ]);

        $response = $this->get('/en/meetings');
        $response->assertOk();

        // City options inside dropdown
        $response->assertSee('<option value="Cairo"', false);
        $response->assertSee('<option value="Alexandria"', false);
        $response->assertSee('<option value="Giza"', false);

        // Fallback filter query on backend
        $cairoFiltered = $this->get('/en/meetings?city=Cairo');
        $cairoFiltered->assertOk();
        $cairoFiltered->assertSee('Zamalek Discussion');
        $cairoFiltered->assertDontSee('Smouha Group');
    }

    /**
     * Acceptance Criterion:
     * Visitor's GPS coordinates are NEVER sent to the server and NEVER logged in web access logs.
     * No HTTP request containing visitor latitude/longitude is dispatched.
     */
    public function test_visitor_gps_coordinates_are_never_sent_to_server_or_logged(): void
    {
        Http::fake();

        // Page visit emits no external tracking requests
        $response = $this->get('/en/meetings');
        $response->assertOk();

        Http::assertNothingSent();

        // Verify HTML form does not have an action posting GPS coordinates to the server
        $html = $response->getContent();
        $this->assertStringNotContainsString('action="/meetings/near"', $html);
        $this->assertStringNotContainsString('name="lat"', $html);
        $this->assertStringNotContainsString('name="latitude"', $html);
        $this->assertStringNotContainsString('name="longitude"', $html);

        // Ensure "Find Meetings Near Me" button is type="button" and does NOT submit a form
        $this->assertMatchesRegularExpression('/<button[^>]*type="button"[^>]*id="find-near-me-btn"/i', $html);
    }

    /**
     * Acceptance Criterion:
     * Distance calculation happens 100% client-side in browser JavaScript using the Haversine formula.
     */
    public function test_haversine_distance_calculation_in_javascript(): void
    {
        $nodeScript = <<<'JS'
import('./resources/js/components/nearby-locator.js').then((module) => {
    const { haversineDistance } = module;

    // Cairo (30.0444, 31.2357) to Giza (30.0131, 31.2089) ~ 4.33 km
    const distGiza = haversineDistance(30.0444, 31.2357, 30.0131, 31.2089);

    // Cairo (30.0444, 31.2357) to Alexandria (31.2001, 29.9187) ~ 179.98 km
    const distAlex = haversineDistance(30.0444, 31.2357, 31.2001, 29.9187);

    // Identical points should be 0 km
    const distZero = haversineDistance(30.0444, 31.2357, 30.0444, 31.2357);

    console.log(JSON.stringify({
        distGiza: Math.round(distGiza * 100) / 100,
        distAlex: Math.round(distAlex * 100) / 100,
        distZero: distZero
    }));
}).catch((err) => {
    console.error(err);
    process.exit(1);
});
JS;

        $process = new Process(['node', '--input-type=module', '-e', $nodeScript], base_path());
        $process->mustRun();

        $output = json_decode($process->getOutput(), true);
        $this->assertIsArray($output);
        $this->assertEqualsWithDelta(4.33, $output['distGiza'], 0.1);
        $this->assertEqualsWithDelta(179.98, $output['distAlex'], 1.0);
        $this->assertSame(0, $output['distZero']);
    }

    /**
     * Acceptance Criterion:
     * Sorts meetings running today by nearest distance.
     */
    public function test_client_side_distance_sorting_sorts_meetings_running_today_by_nearest_distance(): void
    {
        $nodeScript = <<<'JS'
import('./resources/js/components/nearby-locator.js').then((module) => {
    const { sortMeetingsByDistance } = module;

    const userLat = 30.0444; // Downtown Cairo
    const userLon = 31.2357;

    const meetings = [
        { id: 'alex_today', name: 'Alexandria Meeting Today', lat: 31.2001, lng: 29.9187, is_today: true },
        { id: 'cairo_today', name: 'Cairo Meeting Today', lat: 30.0500, lng: 31.2400, is_today: true },
        { id: 'cairo_tomorrow', name: 'Cairo Meeting Tomorrow', lat: 30.0445, lng: 31.2358, is_today: false },
        { id: 'giza_today', name: 'Giza Meeting Today', lat: 30.0131, lng: 31.2089, is_today: true },
    ];

    const sorted = sortMeetingsByDistance(meetings, userLat, userLon);

    console.log(JSON.stringify(sorted.map(m => ({
        id: m.id,
        is_today: m.is_today,
        distance: Math.round(m.distance * 10) / 10
    }))));
}).catch((err) => {
    console.error(err);
    process.exit(1);
});
JS;

        $process = new Process(['node', '--input-type=module', '-e', $nodeScript], base_path());
        $process->mustRun();

        $sorted = json_decode($process->getOutput(), true);
        $this->assertIsArray($sorted);
        $this->assertCount(4, $sorted);

        // First 3 should all be running today (is_today: true)
        $this->assertTrue($sorted[0]['is_today']);
        $this->assertTrue($sorted[1]['is_today']);
        $this->assertTrue($sorted[2]['is_today']);

        // Among today's meetings: Cairo (<1km) -> Giza (~4.3km) -> Alexandria (~180km)
        $this->assertSame('cairo_today', $sorted[0]['id']);
        $this->assertSame('giza_today', $sorted[1]['id']);
        $this->assertSame('alex_today', $sorted[2]['id']);

        // Meeting not running today placed after today's meetings
        $this->assertFalse($sorted[3]['is_today']);
        $this->assertSame('cairo_tomorrow', $sorted[3]['id']);
    }

    /**
     * Test badge formatting produces [X.X km away] string as required.
     */
    public function test_distance_badge_formatting_produces_expected_format(): void
    {
        $nodeScript = <<<'JS'
import('./resources/js/components/nearby-locator.js').then((module) => {
    const { formatDistanceBadge } = module;

    console.log(JSON.stringify({
        badge1: formatDistanceBadge(4.3312),
        badge2: formatDistanceBadge(12.0),
        badge3: formatDistanceBadge(0.54)
    }));
}).catch((err) => {
    console.error(err);
    process.exit(1);
});
JS;

        $process = new Process(['node', '--input-type=module', '-e', $nodeScript], base_path());
        $process->mustRun();

        $badges = json_decode($process->getOutput(), true);
        $this->assertSame('[4.3 km away]', $badges['badge1']);
        $this->assertSame('[12.0 km away]', $badges['badge2']);
        $this->assertSame('[0.5 km away]', $badges['badge3']);
    }

    /**
     * Test inactive meetings are excluded from public meetings page.
     */
    public function test_inactive_meetings_are_excluded_from_public_page(): void
    {
        $group = Group::create([
            'name_en' => 'Zamalek Group',
            'name_ar' => 'مجموعة الزمالك',
            'slug' => 'zamalek-group',
        ]);

        $activeMeeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Active Zamalek Meeting',
            'day_of_week' => 1,
            'city' => 'Cairo',
            'is_active' => true,
        ]);

        $inactiveMeeting = Meeting::create([
            'group_id' => $group->id,
            'name' => 'Inactive Zamalek Meeting',
            'day_of_week' => 1,
            'city' => 'Cairo',
            'is_active' => false,
        ]);

        $response = $this->get('/ar/meetings');
        $response->assertOk();
        $response->assertSee('Active Zamalek Meeting');
        $response->assertDontSee('Inactive Zamalek Meeting');
    }
}
