<?php

namespace Tests\Feature\Public;

use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapTileTest extends TestCase
{
    use RefreshDatabase;

    protected string $tilesStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tilesStoragePath = storage_path('app/tiles');
        if (File::exists($this->tilesStoragePath)) {
            File::deleteDirectory($this->tilesStoragePath);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tilesStoragePath)) {
            File::deleteDirectory($this->tilesStoragePath);
        }

        parent::tearDown();
    }

    /**
     * Test fetching a missing tile from OpenStreetMap and caching it locally.
     */
    public function test_fetches_and_caches_tile_from_upstream(): void
    {
        $fakePngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            'https://tile.openstreetmap.org/5/18/12.png' => Http::response($fakePngContent, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->get('/tiles/5/18/12.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=604800', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($fakePngContent, $response->getContent());

        // Ensure tile is cached locally on disk
        $cachedFilePath = storage_path('app/tiles/5/18/12.png');
        $this->assertFileExists($cachedFilePath);
        $this->assertSame($fakePngContent, file_get_contents($cachedFilePath));

        // Verify User-Agent sent to OpenStreetMap
        Http::assertSent(function ($request) {
            return $request->url() === 'https://tile.openstreetmap.org/5/18/12.png'
                && ! empty($request->header('User-Agent')[0]);
        });
    }

    /**
     * Test serving an already cached tile without sending HTTP request to upstream.
     */
    public function test_serves_cached_tile_without_upstream_request(): void
    {
        $cachedPngContent = 'CACHED_TILE_IMAGE_CONTENT';
        $tileDir = storage_path('app/tiles/5/18');
        File::makeDirectory($tileDir, 0755, true, true);
        File::put($tileDir.'/12.png', $cachedPngContent);

        Http::fake();

        $response = $this->get('/tiles/5/18/12.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=604800', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($cachedPngContent, $response->getContent());

        // No upstream request should be made when tile is cached
        Http::assertNothingSent();
    }

    /**
     * Test zoom level below minimum (5) is rejected with 400 Bad Request.
     */
    public function test_rejects_zoom_level_below_minimum(): void
    {
        $response = $this->get('/tiles/4/9/6.png');

        $response->assertStatus(400);
    }

    /**
     * Test zoom level above maximum (18) is rejected with 400 Bad Request.
     */
    public function test_rejects_zoom_level_above_maximum(): void
    {
        $response = $this->get('/tiles/19/307634/216232.png');

        $response->assertStatus(400);
    }

    /**
     * Test coordinates outside Egyptian geographical bounding box are rejected.
     */
    public function test_rejects_coordinates_outside_egypt(): void
    {
        // Tile covering London (z=5, x=16, y=10)
        $response = $this->get('/tiles/5/16/10.png');

        $response->assertStatus(400);
    }

    /**
     * Test tile request accepts route without .png extension.
     */
    public function test_accepts_tile_request_without_png_extension(): void
    {
        $fakePngContent = 'TEST_TILE_IMAGE';

        Http::fake([
            'https://tile.openstreetmap.org/5/18/12.png' => Http::response($fakePngContent, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->get('/tiles/5/18/12');

        $response->assertOk();
        $this->assertSame($fakePngContent, $response->getContent());
    }

    /**
     * Test upstream failure is handled gracefully.
     */
    public function test_handles_upstream_failure_gracefully(): void
    {
        Http::fake([
            'https://tile.openstreetmap.org/5/18/12.png' => Http::response('Server error', 500),
        ]);

        $response = $this->get('/tiles/5/18/12.png');

        $this->assertGreaterThanOrEqual(400, $response->status());
    }

    /**
     * Test meeting map component renders on public meetings page with bilingual attributes.
     */
    public function test_meeting_map_renders_on_meetings_page(): void
    {
        $group = Group::create([
            'name_en' => 'Cairo Group',
            'name_ar' => 'مجموعة القاهرة',
            'slug' => 'cairo-group',
        ]);

        Meeting::create([
            'group_id' => $group->id,
            'title' => 'Monday Evening Meeting',
            'day_of_week' => 1,
            'time' => '19:00',
            'venue' => 'Zamalek Community Hall',
            'city' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'is_active' => true,
        ]);

        $response = $this->get('/ar/meetings');

        $response->assertOk();
        $response->assertSee('data-meeting-map', false);
        $response->assertSee('data-markers', false);
        $response->assertSee('Cairo Group', false);
        $response->assertSee('مجموعة القاهرة', false);
        $response->assertSee('30.0444', false);
        $response->assertSee('31.2357', false);

        // Public page must emit zero cookies
        $response->assertHeaderMissing('Set-Cookie');
    }
}
