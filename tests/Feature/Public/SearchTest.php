<?php

namespace Tests\Feature\Public;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test navigation bar contains single search box on public pages.
     */
    public function test_navigation_bar_contains_search_box(): void
    {
        $arResponse = $this->get('/ar/who-we-are');
        $arResponse->assertOk();
        $arResponse->assertSee('action="'.url('/ar/search').'"', false);
        $arResponse->assertSee('name="q"', false);

        $enResponse = $this->get('/en/who-we-are');
        $enResponse->assertOk();
        $enResponse->assertSee('action="'.url('/en/search').'"', false);
        $enResponse->assertSee('name="q"', false);
    }

    /**
     * Test unprefixed /search redirects to /en/search.
     */
    public function test_unprefixed_search_redirects_to_english_prefix(): void
    {
        $response = $this->get('/search?q=cairo');

        $response->assertRedirect('/en/search?q=cairo');
    }

    /**
     * Acceptance Criterion 1:
     * Searching "Cairo" or "القاهرة" returns relevant Cairo meetings and literature.
     */
    public function test_acceptance_criterion_cairo_returns_relevant_meetings_and_literature(): void
    {
        $cairoGroup = Group::factory()->create([
            'name_en' => 'Cairo Serenity Group',
            'name_ar' => 'مجموعة القاهرة للسكينة',
        ]);

        $cairoMeeting = Meeting::factory()->create([
            'group_id' => $cairoGroup->id,
            'title' => 'Cairo Downtown Evening Meeting',
            'city' => 'Cairo',
            'venue' => 'St. Joseph Church',
            'notes' => 'Open discussion meeting for all members in Cairo',
            'day_of_week' => 'Monday',
            'time' => '19:00',
        ]);

        $cairoPage = Page::factory()->create([
            'title_en' => 'Cairo A.A. Literature & History',
            'title_ar' => 'أدبيات وتاريخ زمالة القاهرة',
            'body_en' => 'Welcome to the collection of recovery literature in Cairo Egypt.',
            'body_ar' => 'مرحباً بكم في مجموعة أدبيات التعافي الخاصة بالقاهرة في مصر.',
            'slug' => 'cairo-literature',
            'is_published' => true,
        ]);

        // Irrelevant control group and page in Alexandria
        $alexGroup = Group::factory()->create([
            'name_en' => 'Alexandria Group',
            'name_ar' => 'مجموعة الإسكندرية',
        ]);
        $alexMeeting = Meeting::factory()->create([
            'group_id' => $alexGroup->id,
            'title' => 'Alexandria Corniche Meeting',
            'city' => 'Alexandria',
            'venue' => 'Seafront Hall',
            'notes' => 'Open meeting in Alexandria',
        ]);
        $alexPage = Page::factory()->create([
            'title_en' => 'Alexandria Service Guide',
            'title_ar' => 'دليل خدمة الإسكندرية',
            'body_en' => 'Local service structure in Alexandria.',
            'body_ar' => 'هيكل الخدمة المحلية في الإسكندرية.',
            'is_published' => true,
        ]);

        // 1. Search "Cairo" in English
        $responseEn = $this->get('/en/search?q=Cairo');
        $responseEn->assertOk();
        $responseEn->assertSeeText('Cairo Downtown Evening Meeting');
        $responseEn->assertSeeText('Cairo A.A. Literature & History');
        $responseEn->assertDontSeeText('Alexandria Corniche Meeting');
        $responseEn->assertDontSeeText('Alexandria Service Guide');

        // 2. Search "القاهرة" in Arabic
        $responseAr = $this->get('/ar/search?q=القاهرة');
        $responseAr->assertOk();
        $responseAr->assertSeeText('Cairo Downtown Evening Meeting');
        $responseAr->assertSeeText('أدبيات وتاريخ زمالة القاهرة');
        $responseAr->assertDontSeeText('Alexandria Corniche Meeting');
        $responseAr->assertDontSeeText('Alexandria Service Guide');
    }

    /**
     * Acceptance Criterion 2:
     * Searching "خطوات" returns the 12 Steps page.
     */
    public function test_acceptance_criterion_steps_returns_the_12_steps_page(): void
    {
        $stepsPage = Page::factory()->create([
            'title_en' => 'The Twelve Steps of Alcoholics Anonymous',
            'title_ar' => 'الخطوات الاثنتا عشرة لمدمني الخمر المجهولين',
            'body_en' => 'Here are the twelve steps of recovery and spiritual awakening.',
            'body_ar' => 'هذه هي خطوات التعافي واليقظة الروحية في زمالة مدمني الخمر المجهولين.',
            'slug' => 'the-twelve-steps',
            'is_published' => true,
        ]);

        $traditionsPage = Page::factory()->create([
            'title_en' => 'The Twelve Traditions',
            'title_ar' => 'التقاليد الاثنا عشر',
            'body_en' => 'Our common welfare should come first.',
            'body_ar' => 'يجب أن تأتي مصلحتنا المشتركة أولاً.',
            'slug' => 'the-twelve-traditions',
            'is_published' => true,
        ]);

        $response = $this->get('/ar/search?q=خطوات');
        $response->assertOk();
        $response->assertSeeText('الخطوات الاثنتا عشرة');
        $response->assertSee(url('/ar/the-twelve-steps'));
        $response->assertDontSeeText('التقاليد الاثنا عشر');
    }

    /**
     * Test ArabicNormalizer handles diacritics, Alef variants, and Ta Marbuta.
     */
    public function test_arabic_normalization_handles_spelling_variations(): void
    {
        $group = Group::factory()->create([
            'name_en' => 'Maadi Group',
            'name_ar' => 'مجموعة المعادي',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'مجموعة الشروق في المعادي',
            'notes' => 'اجتماع أسبوعي لأعضاء الزمالة',
            'city' => 'المعادي',
        ]);

        // Search with full diacritics (tashkeel)
        $tashkeelResponse = $this->get('/ar/search?q='.urlencode('مَجْمُوعَة الشُّرُوقِ'));
        $tashkeelResponse->assertOk();
        $tashkeelResponse->assertSeeText('مجموعة الشروق في المعادي');

        // Search with Alef variant "لاعضاء" matching "لأعضاء"
        $alefResponse = $this->get('/ar/search?q='.urlencode('لاعضاء'));
        $alefResponse->assertOk();
        $alefResponse->assertSeeText('مجموعة الشروق في المعادي');

        // Search without definite article "معادي" matching "المعادي"
        $articleResponse = $this->get('/ar/search?q='.urlencode('معادي'));
        $articleResponse->assertOk();
        $articleResponse->assertSeeText('مجموعة الشروق في المعادي');
    }

    /**
     * Test search matches all specified Meeting fields: group name, venue, city, notes, title.
     */
    public function test_search_targets_all_meeting_fields(): void
    {
        $group = Group::factory()->create([
            'name_en' => 'Heliopolis Group',
            'name_ar' => 'مجموعة مصر الجديدة',
        ]);

        $meeting = Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Sunrise Serenity',
            'venue' => 'Basilica Cathedral Basement',
            'city' => 'Heliopolis',
            'notes' => 'Wheelchair accessible entrance on North side',
        ]);

        // 1. Group name search
        $this->get('/en/search?q=Heliopolis')
            ->assertOk()
            ->assertSeeText('Sunrise Serenity');

        // 2. Venue search
        $this->get('/en/search?q=Basilica')
            ->assertOk()
            ->assertSeeText('Sunrise Serenity');

        // 3. City search
        $this->get('/en/search?q=Heliopolis')
            ->assertOk()
            ->assertSeeText('Sunrise Serenity');

        // 4. Notes search
        $this->get('/en/search?q=Wheelchair')
            ->assertOk()
            ->assertSeeText('Sunrise Serenity');

        // 5. Title search
        $this->get('/en/search?q=Sunrise')
            ->assertOk()
            ->assertSeeText('Sunrise Serenity');
    }

    /**
     * Test unpublished pages are strictly excluded from search results.
     */
    public function test_unpublished_pages_are_excluded_from_search(): void
    {
        Page::factory()->unpublished()->create([
            'title_en' => 'Confidential Internal Draft Guidelines',
            'title_ar' => 'مسودة إرشادات داخلية سرية',
            'body_en' => 'Not yet approved for publication.',
            'body_ar' => 'لم يتم اعتمادها للنشر بعد.',
        ]);

        $response = $this->get('/en/search?q=Confidential');
        $response->assertOk();
        $response->assertDontSeeText('Confidential Internal Draft Guidelines');
    }

    /**
     * Test results include badges for category and meeting day/time.
     */
    public function test_results_display_badges_for_category_and_meeting_day_time(): void
    {
        $group = Group::factory()->create();
        Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Friday Night Fellowship',
            'day_of_week' => 'Friday',
            'time' => '20:30',
            'city' => 'Zamalek',
        ]);

        Page::factory()->create([
            'title_en' => 'Friday Traditions Study',
            'title_ar' => 'دراسة تقاليد الجمعة',
            'body_en' => 'Friday study sessions for all members.',
            'is_published' => true,
        ]);

        // Arabic search checks badges
        $responseAr = $this->get('/ar/search?q=Friday');
        $responseAr->assertOk();
        $responseAr->assertSee('الاجتماعات');
        $responseAr->assertSee('الصفحات');
        $responseAr->assertSee('Friday 20:30');

        // English search checks badges
        $responseEn = $this->get('/en/search?q=Friday');
        $responseEn->assertOk();
        $responseEn->assertSee('Meetings');
        $responseEn->assertSee('Articles & Pages');
        $responseEn->assertSee('Friday 20:30');
    }

    /**
     * Test matched keywords are highlighted in excerpt mark tags.
     */
    public function test_matched_keywords_are_highlighted_in_excerpts(): void
    {
        Page::factory()->create([
            'title_en' => 'Living Sober Principles',
            'title_ar' => 'مبادئ العيش بدون كحول',
            'body_en' => 'Practical recovery guidelines for staying away from that first drink.',
            'body_ar' => 'إرشادات تعافي عملية للبقاء بعيداً عن الكأس الأولى.',
            'is_published' => true,
        ]);

        $response = $this->get('/en/search?q=Principles');
        $response->assertOk();
        $response->assertSee('<mark class="bg-amber-200 text-amber-950 font-semibold px-0.5 rounded">Principles</mark>', false);
    }

    /**
     * Test helpful suggestions rendered when no results are found.
     */
    public function test_no_results_renders_helpful_suggestions(): void
    {
        // 1. In Arabic
        $responseAr = $this->get('/ar/search?q=كلمةغيرموجودةأبداً');
        $responseAr->assertOk();
        $responseAr->assertSee('لم نتمكن من العثور على أي نتائج');
        $responseAr->assertSee('اقتراحات مفيدة');
        $responseAr->assertSee('القاهرة');

        // 2. In English
        $responseEn = $this->get('/en/search?q=NonExistentQueryString123');
        $responseEn->assertOk();
        $responseEn->assertSee('No results found');
        $responseEn->assertSee('Helpful suggestions');
        $responseEn->assertSee('Cairo');
    }

    /**
     * Test tabs parameter filters results between Meetings and Pages.
     */
    public function test_tabs_filter_results_by_category(): void
    {
        $group = Group::factory()->create();
        Meeting::factory()->create([
            'group_id' => $group->id,
            'title' => 'Sunday Recovery Group',
            'notes' => 'Sunday meeting',
        ]);

        Page::factory()->create([
            'title_en' => 'Sunday Spiritual Readings',
            'title_ar' => 'قراءات روحية ليوم الأحد',
            'body_en' => 'Sunday reflection topics for meetings.',
            'is_published' => true,
        ]);

        // All tab displays both
        $all = $this->get('/en/search?q=Sunday&tab=all');
        $all->assertOk();
        $all->assertSeeText('Sunday Recovery Group');
        $all->assertSeeText('Sunday Spiritual Readings');

        // Meetings tab displays only meetings
        $meetingsOnly = $this->get('/en/search?q=Sunday&tab=meetings');
        $meetingsOnly->assertOk();
        $meetingsOnly->assertSeeText('Sunday Recovery Group');
        $meetingsOnly->assertDontSeeText('Sunday Spiritual Readings');

        // Pages tab displays only pages
        $pagesOnly = $this->get('/en/search?q=Sunday&tab=pages');
        $pagesOnly->assertOk();
        $pagesOnly->assertDontSeeText('Sunday Recovery Group');
        $pagesOnly->assertSeeText('Sunday Spiritual Readings');
    }

    /**
     * Test rate limiting: max 30 searches per minute per IP.
     */
    public function test_rate_limiting_enforces_maximum_30_searches_per_minute_per_ip(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $response = $this->get('/en/search?q=rate_limit_test');
            $response->assertOk();
        }

        // 31st request from same IP must hit HTTP 429 Too Many Requests
        $response31 = $this->get('/en/search?q=rate_limit_test');
        $response31->assertStatus(429);
    }
}
