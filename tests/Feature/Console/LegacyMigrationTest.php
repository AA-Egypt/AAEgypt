<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MigrateLegacyWordPressCommand;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/legacy_migration_test_'.uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_xml_migration_extracts_clean_content_and_sanitizes_html(): void
    {
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wp="http://wordpress.org/export/1.2/">
    <channel>
        <!-- Page with injected malicious content -->
        <item>
            <title>Who We Are</title>
            <link>https://aaegypt.org/who-we-are/</link>
            <wp:post_id>42</wp:post_id>
            <wp:post_name>who-we-are</wp:post_name>
            <wp:post_type>page</wp:post_type>
            <wp:status>publish</wp:status>
            <content:encoded><![CDATA[
                <p>Welcome to <b>AA Egypt</b>. We are a fellowship of recovery.</p>
                <script type="text/javascript">eval(atob("malicious_code"));</script>
                <script src="https://conde-dev.us/payload.js"></script>
                <iframe src="https://slot-gacor-777.com/bonus" width="1" height="1"></iframe>
                <link rel="stylesheet" href="https://conde-dev.us/injected.css">
                <style>body { background: url('https://conde-dev.us/beacon.png'); }</style>
                <p>Find hope and serenity. <a href="https://conde-dev.us/backdoor">Download Free VPN</a></p>
                <p>Special offer: <a href="https://slotgacor88.org">Daftar Slot Gacor Maxwin</a></p>
                <p>Read our literature at <a href="/ar/literature">Literature Page</a></p>
            ]]></content:encoded>
        </item>

        <!-- Meeting item -->
        <item>
            <title>Cairo Fellowship Group Meeting</title>
            <link>https://aaegypt.org/meetings-list/</link>
            <wp:post_id>101</wp:post_id>
            <wp:post_name>cairo-fellowship</wp:post_name>
            <wp:post_type>meeting</wp:post_type>
            <wp:status>publish</wp:status>
            <content:encoded><![CDATA[Open discussion meeting in English and Arabic.]]></content:encoded>
            <wp:postmeta>
                <wp:meta_key>_meeting_day</wp:meta_key>
                <wp:meta_value>Monday</wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key>_meeting_time</wp:meta_key>
                <wp:meta_value>19:30</wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key>_meeting_venue</wp:meta_key>
                <wp:meta_value>St. Andrew Church</wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key>_meeting_city</wp:meta_key>
                <wp:meta_value>Cairo</wp:meta_value>
            </wp:postmeta>
            <wp:postmeta>
                <wp:meta_key>_group_name</wp:meta_key>
                <wp:meta_value>Cairo Group</wp:meta_value>
            </wp:postmeta>
        </item>

        <!-- Menu Item -->
        <item>
            <title>Home En</title>
            <link>https://aaegypt.org/en/home-2</link>
            <wp:post_id>201</wp:post_id>
            <wp:post_name>home-2</wp:post_name>
            <wp:post_type>nav_menu_item</wp:post_type>
            <wp:postmeta>
                <wp:meta_key>_menu_item_url</wp:meta_key>
                <wp:meta_value>https://aaegypt.org/en/home-2</wp:meta_value>
            </wp:postmeta>
        </item>

        <!-- Injected malicious redirect / link -->
        <item>
            <title>Hacked Slot Redirect</title>
            <link>https://conde-dev.us/compromised</link>
            <wp:post_id>999</wp:post_id>
            <wp:post_name>hacked-slot</wp:post_name>
            <wp:post_type>nav_menu_item</wp:post_type>
            <wp:postmeta>
                <wp:meta_key>_menu_item_url</wp:meta_key>
                <wp:meta_value>https://slot-gacor-777.com</wp:meta_value>
            </wp:postmeta>
        </item>
    </channel>
</rss>
XML;

        $xmlFilePath = $this->tempDir.'/export.xml';
        file_put_contents($xmlFilePath, $xmlContent);

        $this->artisan('content:migrate-legacy', ['file' => $xmlFilePath])
            ->assertSuccessful();

        // 1. Verify Page was imported and sanitized
        $page = Page::where('slug', 'who-we-are')->first();
        $this->assertNotNull($page);
        $this->assertEquals('Who We Are', $page->title_en);

        // Assert strictly sanitized HTML
        $body = $page->body_en;
        $this->assertStringContainsString('Welcome to <b>AA Egypt</b>', $body);
        $this->assertStringContainsString('Find hope and serenity', $body);
        $this->assertStringContainsString('<a href="/ar/literature">Literature Page</a>', $body);

        // Assert malicious items eliminated
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('eval(', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('slot-gacor', $body);
        $this->assertStringNotContainsString('slotgacor', $body);
        $this->assertStringNotContainsString('conde-dev.us', $body);
        $this->assertStringNotContainsString('<style', $body);
        $this->assertStringNotContainsString('<link', $body);
        $this->assertStringNotContainsString('Download Free VPN', $body);
        $this->assertStringNotContainsString('Daftar Slot Gacor', $body);

        // 2. Verify Meeting was imported
        $meeting = Meeting::where('title', 'Cairo Fellowship Group Meeting')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('Monday', $meeting->day_of_week);
        $this->assertEquals('19:30', $meeting->time);
        $this->assertEquals('St. Andrew Church', $meeting->venue);
        $this->assertEquals('Cairo', $meeting->city);
        $this->assertEquals('Cairo Group', $meeting->group->name_en);

        // 3. Verify Redirects were created
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/?p=42',
            'target_url' => '/ar/who-we-are',
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/who-we-are/',
            'target_url' => '/ar/who-we-are',
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/meetings-list/',
            'target_url' => '/ar/meetings',
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/en/home-2',
            'target_url' => '/en',
        ]);

        // 4. Assert malicious URLs were never added to redirects
        $this->assertDatabaseMissing('redirects', [
            'target_url' => 'https://slot-gacor-777.com',
        ]);
        $this->assertEquals(0, Redirect::where('source_path', 'like', '%conde-dev.us%')->count());
        $this->assertEquals(0, Redirect::where('target_url', 'like', '%slot-gacor%')->count());
    }

    public function test_json_migration_extracts_and_sanitizes_data(): void
    {
        $jsonData = [
            'pages' => [
                [
                    'id' => 88,
                    'title' => 'Privacy Policy',
                    'slug' => 'privacy',
                    'url' => '/?p=88',
                    'target_url' => '/ar/privacy',
                    'body' => '<p>Privacy information.</p><script>alert("hack");</script><a href="https://conde-dev.us/exploit">Click for VPN</a>',
                ],
            ],
            'meetings' => [
                [
                    'title' => 'Alexandria Sunset Meeting',
                    'day_of_week' => 'Wednesday',
                    'time' => '18:00',
                    'venue' => 'Community Center',
                    'city' => 'Alexandria',
                    'group_name' => 'Alexandria Group',
                    'notes' => '<p>Open meeting.</p><iframe src="https://slot-gacor.com"></iframe>',
                    'url' => '/alexandria-meeting',
                ],
            ],
            'menu_items' => [
                [
                    'title' => 'Meetings List',
                    'url' => '/meetings-list',
                    'target_url' => '/ar/meetings',
                ],
                [
                    'title' => 'Malicious Link',
                    'url' => 'https://conde-dev.us/bad',
                    'target_url' => 'https://slot88-gacor.com',
                ],
            ],
            'redirects' => [
                [
                    'source_path' => '/old-about',
                    'target_url' => '/ar/who-we-are',
                ],
                [
                    'source_path' => '/bad-redirect',
                    'target_url' => 'https://conde-dev.us/redirect',
                ],
            ],
        ];

        $jsonFilePath = $this->tempDir.'/export.json';
        file_put_contents($jsonFilePath, json_encode($jsonData));

        $this->artisan('content:migrate-legacy', ['file' => $jsonFilePath])
            ->assertSuccessful();

        $page = Page::where('slug', 'privacy')->first();
        $this->assertNotNull($page);
        $this->assertStringNotContainsString('<script', $page->body_en);
        $this->assertStringNotContainsString('conde-dev.us', $page->body_en);
        $this->assertStringNotContainsString('Click for VPN', $page->body_en);

        $meeting = Meeting::where('title', 'Alexandria Sunset Meeting')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('Alexandria', $meeting->city);
        $this->assertStringNotContainsString('<iframe', $meeting->notes);

        $this->assertDatabaseHas('redirects', [
            'source_path' => '/?p=88',
            'target_url' => '/ar/privacy',
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/meetings-list',
            'target_url' => '/ar/meetings',
        ]);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/old-about',
            'target_url' => '/ar/who-we-are',
        ]);

        $this->assertEquals(0, Redirect::where('target_url', 'like', '%conde-dev.us%')->count());
        $this->assertEquals(0, Redirect::where('target_url', 'like', '%slot88%')->count());
    }

    public function test_legacy_redirects_middleware_returns_301_and_increments_hit_count(): void
    {
        Redirect::create([
            'source_path' => '/?p=42',
            'target_url' => '/ar/who-we-are',
            'hit_count' => 0,
        ]);

        Redirect::create([
            'source_path' => '/meetings-list',
            'target_url' => '/ar/meetings',
            'hit_count' => 0,
        ]);

        Redirect::create([
            'source_path' => '/en/home-2',
            'target_url' => '/en',
            'hit_count' => 0,
        ]);

        // 1. Test /?p=42 returns 301 and increments hit count
        $response = $this->get('/?p=42');
        $response->assertStatus(301);
        $response->assertRedirect('/ar/who-we-are');

        $redirect1 = Redirect::where('source_path', '/?p=42')->first();
        $this->assertEquals(1, $redirect1->hit_count);

        // Second hit increments to 2
        $this->get('/?p=42')->assertStatus(301);
        $redirect1->refresh();
        $this->assertEquals(2, $redirect1->hit_count);

        // 2. Test /meetings-list returns 301
        $response = $this->get('/meetings-list');
        $response->assertStatus(301);
        $response->assertRedirect('/ar/meetings');

        $redirect2 = Redirect::where('source_path', '/meetings-list')->first();
        $this->assertEquals(1, $redirect2->hit_count);

        // Test with trailing slash matches /meetings-list
        $response = $this->get('/meetings-list/');
        $response->assertStatus(301);
        $response->assertRedirect('/ar/meetings');
        $redirect2->refresh();
        $this->assertEquals(2, $redirect2->hit_count);

        // 3. Test /en/home-2 returns 301
        $response = $this->get('/en/home-2');
        $response->assertStatus(301);
        $response->assertRedirect('/en');

        $redirect3 = Redirect::where('source_path', '/en/home-2')->first();
        $this->assertEquals(1, $redirect3->hit_count);
    }

    public function test_legacy_redirects_middleware_passes_through_non_legacy_requests(): void
    {
        // Standard route request without redirect
        $response = $this->get('/ar');
        $response->assertStatus(200);
    }

    public function test_html_sanitizer_removes_scripts_iframes_styles_and_injected_backlinks(): void
    {
        /** @var MigrateLegacyWordPressCommand $command */
        $command = app(MigrateLegacyWordPressCommand::class);

        $dirtyHtml = <<<'HTML'
            <p>Clean intro paragraph with <b>bold text</b> and <i>italic text</i>.</p>
            <script>alert('XSS');</script>
            <script src="https://conde-dev.us/hook.js"></script>
            <iframe src="https://slot-gacor.com" style="display:none"></iframe>
            <style>body { color: red; }</style>
            <link rel="stylesheet" href="https://conde-dev.us/evil.css">
            <p style="font-size:20px" onclick="alert(1)">Some clean styled text</p>
            <a href="https://conde-dev.us/steal">Download Free VPN</a>
            <a href="https://best-slotgacor-777.com/register">Daftar Slot Gacor</a>
            <a href="javascript:void(0)">Javascript link</a>
            <a href="/ar/who-we-are">Legitimate Link</a>
            <ul>
                <li>First clean item</li>
                <li>Second clean item</li>
            </ul>
HTML;

        $cleanHtml = $command->sanitizeHtml($dirtyHtml);

        $this->assertStringNotContainsString('<script', $cleanHtml);
        $this->assertStringNotContainsString('alert(', $cleanHtml);
        $this->assertStringNotContainsString('<iframe', $cleanHtml);
        $this->assertStringNotContainsString('<style', $cleanHtml);
        $this->assertStringNotContainsString('<link', $cleanHtml);
        $this->assertStringNotContainsString('onclick', $cleanHtml);
        $this->assertStringNotContainsString('style=', $cleanHtml);
        $this->assertStringNotContainsString('conde-dev.us', $cleanHtml);
        $this->assertStringNotContainsString('slot-gacor', $cleanHtml);
        $this->assertStringNotContainsString('Download Free VPN', $cleanHtml);
        $this->assertStringNotContainsString('Daftar Slot Gacor', $cleanHtml);
        $this->assertStringNotContainsString('javascript:', $cleanHtml);

        // Retains legitimate tags and content
        $this->assertStringContainsString('<p>Clean intro paragraph with <b>bold text</b> and <i>italic text</i>.</p>', $cleanHtml);
        $this->assertStringContainsString('Some clean styled text', $cleanHtml);
        $this->assertStringContainsString('<a href="/ar/who-we-are">Legitimate Link</a>', $cleanHtml);
        $this->assertStringContainsString('<li>First clean item</li>', $cleanHtml);
    }

    public function test_malicious_url_validation_detects_all_patterns(): void
    {
        /** @var MigrateLegacyWordPressCommand $command */
        $command = app(MigrateLegacyWordPressCommand::class);

        $this->assertTrue($command->isMaliciousUrl('https://conde-dev.us/payload'));
        $this->assertTrue($command->isMaliciousUrl('http://slot-gacor-88.com'));
        $this->assertTrue($command->isMaliciousUrl('https://judi-online-terpercaya.com'));
        $this->assertTrue($command->isMaliciousUrl('https://casino-betting.org'));
        $this->assertTrue($command->isMaliciousUrl('https://free-vpn-crack.com'));
        $this->assertTrue($command->isMaliciousUrl('javascript:alert(1)'));
        $this->assertTrue($command->isMaliciousUrl('data:text/html,<script>alert(1)</script>'));

        $this->assertFalse($command->isMaliciousUrl('/?p=42'));
        $this->assertFalse($command->isMaliciousUrl('/meetings-list'));
        $this->assertFalse($command->isMaliciousUrl('/en/home-2'));
        $this->assertFalse($command->isMaliciousUrl('https://aaegypt.org/who-we-are'));
        $this->assertFalse($command->isMaliciousUrl('/ar/who-we-are'));
    }

    public function test_command_fails_gracefully_when_file_not_found(): void
    {
        $this->artisan('content:migrate-legacy', ['file' => 'non_existent_file.xml'])
            ->assertFailed()
            ->expectsOutputToContain('Export file not found');
    }
}
