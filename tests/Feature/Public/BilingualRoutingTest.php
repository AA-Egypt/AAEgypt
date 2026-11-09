<?php

namespace Tests\Feature\Public;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BilingualRoutingTest extends TestCase
{
    /**
     * Test root URL automatically redirects to /en.
     */
    public function test_root_url_redirects_to_english_prefix(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/en');
    }

    /**
     * Test Arabic pages render dir=rtl and lang=ar.
     */
    public function test_visiting_ar_who_we_are_renders_rtl_direction(): void
    {
        $response = $this->get('/ar/who-we-are');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
        $this->assertSame('ar', App::getLocale());
    }

    /**
     * Test English pages render dir=ltr and lang=en.
     */
    public function test_visiting_en_who_we_are_renders_ltr_direction(): void
    {
        $response = $this->get('/en/who-we-are');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('lang="en"', false);
        $this->assertSame('en', App::getLocale());
    }

    /**
     * Test language switcher preserves slug from Arabic to English.
     */
    public function test_language_switcher_preserves_slug_from_arabic_to_english(): void
    {
        $response = $this->get('/ar/who-we-are');

        $response->assertOk();
        $response->assertSee('/en/who-we-are');
    }

    /**
     * Test language switcher preserves slug from English to Arabic.
     */
    public function test_language_switcher_preserves_slug_from_english_to_arabic(): void
    {
        $response = $this->get('/en/who-we-are');

        $response->assertOk();
        $response->assertSee('/ar/who-we-are');
    }

    /**
     * Test language switcher toggles root locales correctly.
     */
    public function test_language_switcher_toggles_root_locale(): void
    {
        $arResponse = $this->get('/ar');
        $arResponse->assertOk();
        $arResponse->assertSee('href="'.url('/en').'"', false);

        $enResponse = $this->get('/en');
        $enResponse->assertOk();
        $enResponse->assertSee('href="'.url('/ar').'"', false);
    }

    /**
     * Test header contains mobile-responsive hamburger menu.
     */
    public function test_header_contains_hamburger_menu(): void
    {
        $response = $this->get('/ar/who-we-are');

        $response->assertOk();
        $response->assertSee('id="mobile-menu-toggle"', false);
        $response->assertSee('id="mobile-menu"', false);
    }

    /**
     * Test footer displays AA declaration, disclaimer, and privacy link in Arabic.
     */
    public function test_footer_displays_required_elements_in_arabic(): void
    {
        $response = $this->get('/ar/who-we-are');

        $response->assertOk();
        $response->assertSee('أنا مسؤول');
        $response->assertSee('لا ترتبط بأي طائفة');
        $response->assertSee('/ar/privacy');
    }

    /**
     * Test footer displays AA declaration, disclaimer, and privacy link in English.
     */
    public function test_footer_displays_required_elements_in_english(): void
    {
        $response = $this->get('/en/who-we-are');

        $response->assertOk();
        $response->assertSee('I am responsible');
        $response->assertSee('Alcoholics Anonymous is not allied');
        $response->assertSee('/en/privacy');
    }

    /**
     * Test unprefixed public routes redirect to /en prefix.
     */
    public function test_unprefixed_public_routes_redirect_to_english_prefix(): void
    {
        $this->get('/who-we-are')->assertRedirect('/en/who-we-are');
        $this->get('/privacy')->assertRedirect('/en/privacy');
    }

    /**
     * Test fonts are bundled locally with zero external font CDNs.
     */
    public function test_zero_external_font_cdns_and_local_fonts_bundled(): void
    {
        $this->assertTrue(File::exists(public_path('fonts/cairo.woff2')));
        $this->assertTrue(File::exists(public_path('fonts/tajawal-regular.woff2')));

        $response = $this->get('/ar/who-we-are');
        $content = $response->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $content);
        $this->assertStringNotContainsString('fonts.gstatic.com', $content);
        $this->assertStringNotContainsString('fonts.bunny.net', $content);
    }
}
