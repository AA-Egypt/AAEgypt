<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Announcement;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentContentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createWebAdmin(): User
    {
        return User::factory()->webAdmin()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
    }

    protected function createGsr(): User
    {
        return User::factory()->gsr()->create([
            'must_change_password' => false,
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);
    }

    public function test_unauthenticated_user_is_redirected_from_admin_panel(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_users_are_rejected_from_admin_panel(): void
    {
        $gsr = $this->createGsr();

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/admin');

        $response->assertForbidden();
    }

    public function test_web_admin_can_access_filament_admin_panel(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin');

        $response->assertOk();
    }

    public function test_filament_panel_renders_arabic_rtl_interface_when_arabic_locale_selected(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession([
                'mfa_verified' => true,
                'locale' => 'ar',
            ])
            ->get('/admin?locale=ar');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
    }

    public function test_filament_panel_renders_ltr_interface_when_english_locale_selected(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession([
                'mfa_verified' => true,
                'locale' => 'en',
            ])
            ->get('/admin?locale=en');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
    }

    public function test_filament_panel_defaults_to_english_ltr_interface(): void
    {
        $admin = $this->createWebAdmin();

        $response = $this->actingAs($admin)
            ->withSession([
                'mfa_verified' => true,
            ])
            ->get('/admin');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
    }

    public function test_non_admin_users_cannot_access_page_resource(): void
    {
        $gsr = $this->createGsr();

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/pages');

        $response->assertForbidden();
    }

    public function test_web_admin_can_view_pages_list(): void
    {
        $admin = $this->createWebAdmin();
        $page = Page::factory()->create([
            'title_en' => 'Community Standards',
            'title_ar' => 'معايير المجتمع',
            'slug' => 'community-standards',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/pages');

        $response->assertOk();
        $response->assertSee('Community Standards');
    }

    public function test_web_admin_can_create_bilingual_page_via_filament(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);

        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->fillForm([
                'title_ar' => 'عن الزمالة في مصر',
                'title_en' => 'About The Fellowship in Egypt',
                'slug' => 'about-fellowship-egypt',
                'body_ar' => '<h1>مرحبا بكم</h1><p>تفاصيل الزمالة</p>',
                'body_en' => '<h1>Welcome</h1><p>Fellowship details</p>',
                'meta_description_ar' => 'وصف الصفحة بالعربية',
                'meta_description_en' => 'Description in English',
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', [
            'slug' => 'about-fellowship-egypt',
            'title_ar' => 'عن الزمالة في مصر',
            'title_en' => 'About The Fellowship in Egypt',
            'is_published' => true,
        ]);
    }

    public function test_slug_auto_generation_when_creating_page_without_explicit_slug(): void
    {
        $page = Page::create([
            'title_ar' => 'الأسئلة الشائعة',
            'title_en' => 'Frequently Asked Questions',
            'body_ar' => 'محتوى تجريبي',
            'body_en' => 'Test content',
            'is_published' => true,
        ]);

        $this->assertSame('frequently-asked-questions', $page->slug);

        $arabicOnlyPage = Page::create([
            'title_ar' => 'معلومات إضافية',
            'title_en' => '',
            'body_ar' => 'محتوى تجريبي',
            'body_en' => 'Test content',
            'is_published' => true,
        ]);

        $this->assertNotEmpty($arabicOnlyPage->slug);
    }

    public function test_web_admin_can_edit_and_publish_page(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);
        $page = Page::factory()->unpublished()->create([
            'title_en' => 'Draft Page',
            'slug' => 'draft-page',
        ]);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'title_en' => 'Published Page Title',
                'is_published' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Published Page Title', $page->title_en);
        $this->assertTrue($page->is_published);
    }

    public function test_web_admin_can_delete_page(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);
        $page = Page::factory()->create([
            'title_en' => 'Page to Delete',
            'slug' => 'page-to-delete',
        ]);

        Livewire::actingAs($admin)
            ->test(ListPages::class)
            ->callTableAction(DeleteAction::class, $page);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_non_admin_users_cannot_access_announcement_resource(): void
    {
        $gsr = $this->createGsr();

        $response = $this->actingAs($gsr)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/announcements');

        $response->assertForbidden();
    }

    public function test_web_admin_can_view_announcements_list(): void
    {
        $admin = $this->createWebAdmin();
        Announcement::factory()->create([
            'title_en' => 'Annual Convention 2027',
            'title_ar' => 'المؤتمر السنوي 2027',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['mfa_verified' => true])
            ->get('/admin/announcements');

        $response->assertOk();
        $response->assertSee('Annual Convention 2027');
    }

    public function test_web_admin_can_create_bilingual_announcement(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);

        Livewire::actingAs($admin)
            ->test(CreateAnnouncement::class)
            ->fillForm([
                'title_ar' => 'اجتماع طارئ للخدمة',
                'title_en' => 'Emergency Service Meeting',
                'content_ar' => 'سيتم عقد الاجتماع يوم الأحد.',
                'content_en' => 'Meeting will take place on Sunday.',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-08',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('announcements', [
            'title_ar' => 'اجتماع طارئ للخدمة',
            'title_en' => 'Emergency Service Meeting',
            'is_active' => true,
        ]);
    }

    public function test_web_admin_can_edit_announcement(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);
        $announcement = Announcement::factory()->create([
            'title_en' => 'Old Announcement Title',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm([
                'title_en' => 'Updated Announcement Title',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $announcement->refresh();
        $this->assertSame('Updated Announcement Title', $announcement->title_en);
        $this->assertFalse($announcement->is_active);
    }

    public function test_web_admin_can_delete_announcement(): void
    {
        $admin = $this->createWebAdmin();
        session(['mfa_verified' => true]);
        $announcement = Announcement::factory()->create([
            'title_en' => 'Announcement to Delete',
        ]);

        Livewire::actingAs($admin)
            ->test(ListAnnouncements::class)
            ->callTableAction(DeleteAction::class, $announcement);

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_public_controller_displays_published_page_in_arabic(): void
    {
        $page = Page::factory()->create([
            'slug' => 'bilingual-test-page',
            'title_ar' => 'عنوان تجريبي بالعربية',
            'title_en' => 'English Test Title',
            'body_ar' => 'محتوى عربي ظاهر للجميع.',
            'body_en' => 'English visible content.',
            'is_published' => true,
        ]);

        $response = $this->get('/ar/bilingual-test-page');

        $response->assertOk();
        $response->assertSee('عنوان تجريبي بالعربية');
        $response->assertSee('محتوى عربي ظاهر للجميع.');
        $response->assertSee('dir="rtl"', false);
    }

    public function test_public_controller_displays_published_page_in_english(): void
    {
        $page = Page::factory()->create([
            'slug' => 'bilingual-test-page',
            'title_ar' => 'عنوان تجريبي بالعربية',
            'title_en' => 'English Test Title',
            'body_ar' => 'محتوى عربي ظاهر للجميع.',
            'body_en' => 'English visible content.',
            'is_published' => true,
        ]);

        $response = $this->get('/en/bilingual-test-page');

        $response->assertOk();
        $response->assertSee('English Test Title');
        $response->assertSee('English visible content.');
        $response->assertSee('dir="ltr"', false);
    }

    public function test_public_controller_returns_404_for_unpublished_page(): void
    {
        $page = Page::factory()->unpublished()->create([
            'slug' => 'secret-draft-page',
        ]);

        $response = $this->get('/ar/secret-draft-page');
        $response->assertNotFound();

        $responseEn = $this->get('/en/secret-draft-page');
        $responseEn->assertNotFound();
    }

    public function test_public_controller_returns_404_for_non_existent_page(): void
    {
        $response = $this->get('/ar/completely-non-existent-slug-xyz');
        $response->assertNotFound();
    }
}
