<?php

namespace Tests\Feature\Compliance;

use App\Http\Middleware\StripPublicCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ZeroCookieComplianceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Internal domains allowed for asset requests and links.
     */
    protected array $allowedInternalDomains = [
        'localhost',
        '127.0.0.1',
        'aaegypt.org',
        'staging.aaegypt.org',
        'mail.aaegypt.org',
    ];

    /**
     * List of public routes that must never emit cookies.
     */
    protected array $publicRoutes = [
        '/',
        '/ar',
        '/en',
        '/meetings',
        '/ar/meetings',
        '/en/meetings',
        '/privacy',
        '/ar/privacy',
        '/en/privacy',
        '/who-we-are',
        '/ar/who-we-are',
        '/en/who-we-are',
        '/ar/search',
        '/en/search',
    ];

    /**
     * Test that all public routes strictly emit ZERO cookies and zero tracking headers.
     */
    public function test_all_public_routes_emit_zero_cookies(): void
    {
        foreach ($this->publicRoutes as $route) {
            $response = $this->get($route);

            // Assert Set-Cookie header is completely absent
            $response->assertHeaderMissing('Set-Cookie');

            // Assert response cookie jar contains zero cookies
            $cookies = $response->headers->getCookies();
            $this->assertEmpty(
                $cookies,
                "Public route [{$route}] emitted ".count($cookies).' cookie(s): '.json_encode(array_map(fn ($c) => $c->getName(), $cookies))
            );

            // Assert tracking headers are missing
            $response->assertHeaderMissing('X-Tracking-Id');
            $response->assertHeaderMissing('X-Visitor-Id');
            $response->assertHeaderMissing('X-Session-Id');
        }
    }

    /**
     * Test that public pages scan clean of third-party domains in src and href attributes.
     */
    public function test_public_pages_make_zero_requests_to_third_party_domains(): void
    {
        $contentRoutes = [
            '/ar',
            '/en',
            '/ar/meetings',
            '/en/meetings',
            '/ar/privacy',
            '/en/privacy',
            '/ar/who-we-are',
            '/en/who-we-are',
            '/ar/search',
            '/en/search',
        ];

        foreach ($contentRoutes as $route) {
            $response = $this->get($route);
            $response->assertOk();

            $html = $response->getContent();

            // Scan response HTML for all src and href attributes with absolute HTTP(S) URLs
            preg_match_all('/(?:src|href)=["\'](https?:\/\/[^"\'>\s]+)["\']/i', $html, $matches);
            $urls = $matches[1] ?? [];

            foreach ($urls as $url) {
                $host = parse_url($url, PHP_URL_HOST);

                $this->assertNotNull($host, "Malformed URL found in [{$route}]: {$url}");

                $isInternal = in_array(strtolower($host), $this->allowedInternalDomains, true)
                    || str_ends_with(strtolower($host), '.aaegypt.org');

                $this->assertTrue(
                    $isInternal,
                    "Disallowed external domain [{$host}] detected in asset URL [{$url}] on public route [{$route}]."
                );
            }

            // Explicit assertion: zero external Google Fonts, external CDNs, analytics, ad networks, or embeds
            $disallowedPatterns = [
                'fonts.googleapis.com',
                'fonts.gstatic.com',
                'fonts.bunny.net',
                'cdnjs.cloudflare.com',
                'cdn.jsdelivr.net',
                'unpkg.com',
                'google-analytics.com',
                'googletagmanager.com',
                'doubleclick.net',
                'youtube.com/embed',
                'youtube-nocookie.com',
                'player.vimeo.com',
            ];

            foreach ($disallowedPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $html,
                    "Public route [{$route}] contains third-party asset pattern: {$pattern}"
                );
            }
        }
    }

    /**
     * Test that the Privacy Policy page renders the exact legal text from Appendices A and B.
     */
    public function test_privacy_policy_renders_appendices_a_and_b_with_egyptian_disclosures(): void
    {
        // 1. Verify unprefixed /privacy redirects to /ar/privacy with zero cookies
        $redirectResponse = $this->get('/privacy');
        $redirectResponse->assertRedirect('/ar/privacy');
        $redirectResponse->assertHeaderMissing('Set-Cookie');

        // 2. Arabic Privacy Policy Verification
        $arResponse = $this->get('/ar/privacy');
        $arResponse->assertOk();
        $arResponse->assertHeaderMissing('Set-Cookie');

        // Verify Appendix A: Statutory Disclosures under Egyptian Law 151 of 2020
        $arResponse->assertSee('الملحق (أ)');
        $arResponse->assertSee('قانون حماية البيانات الشخصية المصري رقم 151 لسنة 2020');
        $arResponse->assertSee('المتحكم في البيانات');
        $arResponse->assertSee('مركز حماية البيانات الشخصية');
        $arResponse->assertSee('حق العلم والاطلاع');
        $arResponse->assertSee('حق الرجوع والاعتراض');
        $arResponse->assertSee('حق التصحيح والتعديل');
        $arResponse->assertSee('حق المحو والحذف');
        $arResponse->assertSee('نقل البيانات عبر الحدود');
        $arResponse->assertSee('Zero Cookies');

        // Verify Appendix B: Fellowship Privacy & Traditions
        $arResponse->assertSee('الملحق (ب)');
        $arResponse->assertSee('مبادئ خصوصية وسرية الزمالة');
        $arResponse->assertSee('الأساس الروحي للتخفي والسرية');
        $arResponse->assertSee('التقليد الثاني عشر');
        $arResponse->assertSee('التقليد الحادي عشر');
        $arResponse->assertSee('التقليد السادس');
        $arResponse->assertSee('الجذب لا الترويج');

        // 3. English Privacy Policy Verification
        $enResponse = $this->get('/en/privacy');
        $enResponse->assertOk();
        $enResponse->assertHeaderMissing('Set-Cookie');

        // Verify Appendix A: Statutory Disclosures
        $enResponse->assertSee('Appendix A');
        $enResponse->assertSee('Egyptian Personal Data Protection Law No. 151 of 2020');
        $enResponse->assertSee('Data Controller');
        $enResponse->assertSee('Egyptian Personal Data Protection Center');
        $enResponse->assertSee('Right to Know');
        $enResponse->assertSee('Right to Revoke');
        $enResponse->assertSee('Right to Rectification');
        $enResponse->assertSee('Right to Erasure');
        $enResponse->assertSee('Cross-Border Data Transfers');
        $enResponse->assertSee('Zero-Cookie Architecture');

        // Verify Appendix B: Fellowship Privacy & Traditions
        $enResponse->assertSee('Appendix B');
        $enResponse->assertSee('Fellowship Privacy and Anonymity Principles');
        $enResponse->assertSee('Spiritual Foundation of Anonymity');
        $enResponse->assertSee('Tradition Twelve');
        $enResponse->assertSee('Tradition Eleven');
        $enResponse->assertSee('Tradition Six');
        $enResponse->assertSee('attraction rather than promotion');
    }

    /**
     * Test that StripPublicCookies middleware actively strips cookies from responses.
     */
    public function test_strip_public_cookies_middleware_removes_set_cookie_completely(): void
    {
        $middleware = new StripPublicCookies;

        $request = Request::create('/ar/meetings', 'GET');

        $response = $middleware->handle($request, function () {
            return response('Public Content')
                ->withCookie(cookie('tracking_cookie', 'evil_value'))
                ->withCookie(cookie('session_id', '123456'));
        });

        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertEmpty($response->headers->getCookies());
    }

    /**
     * Test that admin and service routes retain cookies for authentication and CSRF.
     */
    public function test_admin_and_service_routes_retain_cookies_for_authentication(): void
    {
        $middleware = new StripPublicCookies;

        // Non-public service login route
        $loginRequest = Request::create('/login', 'GET');
        $this->assertFalse($middleware->shouldStripCookies($loginRequest));

        // Admin route
        $adminRequest = Request::create('/admin', 'GET');
        $this->assertFalse($middleware->shouldStripCookies($adminRequest));

        // Service portal route
        $serviceRequest = Request::create('/service/app-passwords', 'GET');
        $this->assertFalse($middleware->shouldStripCookies($serviceRequest));

        // Actual HTTP request to /login receives session/CSRF cookies
        $response = $this->get('/login');
        $this->assertNotEmpty($response->headers->getCookies());
        $this->assertTrue($response->headers->has('Set-Cookie'));
    }

    /**
     * Test that HTML scanner detects and fails if external script or CSS URL is introduced.
     */
    public function test_scanner_detects_disallowed_external_assets(): void
    {
        $maliciousHtml = '<html><head><script src="https://evil-cdn.com/tracker.js"></script><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto"></head><body><h1>Hello</h1></body></html>';

        preg_match_all('/(?:src|href)=["\'](https?:\/\/[^"\'>\s]+)["\']/i', $maliciousHtml, $matches);
        $externalUrls = $matches[1] ?? [];

        $disallowed = [];
        foreach ($externalUrls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if (! in_array(strtolower($host), $this->allowedInternalDomains, true) && ! str_ends_with(strtolower($host), '.aaegypt.org')) {
                $disallowed[] = $url;
            }
        }

        $this->assertCount(2, $disallowed);
        $this->assertContains('https://evil-cdn.com/tracker.js', $disallowed);
        $this->assertContains('https://fonts.googleapis.com/css?family=Roboto', $disallowed);
    }
}
