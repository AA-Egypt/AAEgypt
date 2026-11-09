<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportWordPressDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_wordpress_command_dry_run(): void
    {
        $this->artisan('app:import-wordpress', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('Imported 11 meetings into 9 groups.')
            ->expectsOutputToContain('Imported 3 static pages.')
            ->assertSuccessful();

        $this->assertSame(0, Group::count());
        $this->assertSame(0, Meeting::count());
        $this->assertSame(0, Page::count());
    }

    public function test_import_wordpress_command_fresh_import(): void
    {
        // Create dummy data that should be wiped by --fresh
        $dummyGroup = Group::create(['name_en' => 'Dummy Group', 'name_ar' => 'مجموعة وهمية', 'slug' => 'dummy-group']);
        Meeting::create(['group_id' => $dummyGroup->id, 'name' => 'Dummy Meeting', 'title' => 'Dummy Meeting']);

        $this->artisan('app:import-wordpress', ['--fresh' => true])
            ->expectsOutputToContain('Existing data cleared.')
            ->expectsOutputToContain('Imported 11 meetings into 9 groups.')
            ->expectsOutputToContain('Imported 3 static pages.')
            ->assertSuccessful();

        // Verify Groups
        $this->assertSame(9, Group::count());
        $this->assertDatabaseHas('groups', ['slug' => 'aa-zayed-group', 'name_ar' => 'مجموعة زايد']);
        $this->assertDatabaseHas('groups', ['slug' => 'keep-coming-back-group', 'name_en' => 'Keep Coming Back Group']);
        $this->assertDatabaseHas('groups', ['slug' => 'el-gouna-group', 'name_en' => 'El Gouna Group']);

        // Verify Meetings
        $this->assertSame(11, Meeting::count());
        $this->assertDatabaseHas('meetings', [
            'name' => 'Keep Coming Back Sunday (English)',
            'city' => 'Cairo',
            'day_of_week' => 0,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'type' => 'in_person',
            'language' => 'en',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('meetings', [
            'name' => '(Arabic Zoom) زووم عربى',
            'city' => 'Online',
            'day_of_week' => 1,
            'type' => 'online',
            'language' => 'ar',
            'is_active' => true,
        ]);

        // Verify Pages
        $this->assertSame(3, Page::count());
        $whoWeAre = Page::where('slug', 'who-we-are')->first();
        $this->assertNotNull($whoWeAre);
        $this->assertSame('Who We Are', $whoWeAre->title_en);
        $this->assertSame('من نحن', $whoWeAre->title_ar);
        $this->assertStringContainsString('Alcoholics Anonymous', $whoWeAre->body_en);
        $this->assertStringContainsString('مدمنو الخمر المجهولون', $whoWeAre->body_ar);

        $contactUs = Page::where('slug', 'contact-us')->first();
        $this->assertNotNull($contactUs);
        $this->assertSame('Reach Out', $contactUs->title_en);
        $this->assertSame('تواصل معنا', $contactUs->title_ar);

        // Verify Documents
        $this->assertGreaterThan(0, Document::count());

        // Verify Logo and Emblem were copied to public assets
        $this->assertFileExists(public_path('images/emblem.png'));
        $this->assertFileExists(public_path('images/logo.jpg'));
    }

    public function test_import_wordpress_command_fails_gracefully_with_invalid_path(): void
    {
        $this->artisan('app:import-wordpress', ['--wp-path' => '/nonexistent/path'])
            ->expectsOutputToContain('WordPress directory or wp-config.php not found')
            ->assertFailed();
    }
}
