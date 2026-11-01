<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\Redirect;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateLegacyWordPressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:migrate-legacy {file : Path to the export XML or JSON file} {--dry-run : Run without persisting changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate reviewed clean content, meetings, menu items, and 301 redirects from WordPress export';

    /**
     * Known malicious domain substrings or keywords.
     *
     * @var array<int, string>
     */
    protected array $maliciousKeywords = [
        'conde-dev.us',
        'slot gacor',
        'slot-gacor',
        'slotgacor',
        'slot88',
        'gacor',
        'judi online',
        'judi bola',
        'judionline',
        'agen judi',
        'judi',
        'casino online',
        'online casino',
        'casino',
        'gambling',
        'betting',
        'taruhan',
        'togel',
        'sbobet',
        'maxbet',
        'poker88',
        'poker online',
        'pragmatic88',
        'fastestvpn',
        'nordvpn-crack',
        'free vpn',
        'free-vpn',
        'vpn-deal',
        'buy-vpn',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fileArgument = $this->argument('file');
        $filePath = file_exists($fileArgument) ? $fileArgument : base_path($fileArgument);

        if (! file_exists($filePath)) {
            $this->error("Export file not found: {$fileArgument}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be saved to the database.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $content = file_get_contents($filePath);

        if ($content === false) {
            $this->error("Unable to read file: {$filePath}");

            return self::FAILURE;
        }

        $trimmed = ltrim($content);
        $isJson = $extension === 'json' || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');

        try {
            if ($isJson) {
                $this->processJson($content, $dryRun);
            } else {
                $this->processXml($filePath, $content, $dryRun);
            }
        } catch (\Throwable $e) {
            $this->error("Migration failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Legacy WordPress migration completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Process JSON format export.
     */
    protected function processJson(string $jsonContent, bool $dryRun): void
    {
        $data = json_decode($jsonContent, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON data provided.');
        }

        // Support array of items or grouped collections
        $pages = $data['pages'] ?? [];
        $meetings = $data['meetings'] ?? [];
        $menuItems = $data['menu_items'] ?? $data['menus'] ?? [];
        $redirects = $data['redirects'] ?? [];

        // If top-level array of items
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $item) {
                $type = $item['post_type'] ?? $item['type'] ?? 'page';
                if ($type === 'meeting' || $type === 'schedule') {
                    $meetings[] = $item;
                } elseif ($type === 'nav_menu_item' || $type === 'menu_item') {
                    $menuItems[] = $item;
                } elseif ($type === 'redirect') {
                    $redirects[] = $item;
                } else {
                    $pages[] = $item;
                }
            }
        }

        foreach ($pages as $page) {
            $this->importPage($page, $dryRun);
        }

        foreach ($meetings as $meeting) {
            $this->importMeeting($meeting, $dryRun);
        }

        foreach ($menuItems as $menuItem) {
            $this->importMenuItem($menuItem, $dryRun);
        }

        foreach ($redirects as $redirect) {
            $this->importRedirect($redirect, $dryRun);
        }
    }

    /**
     * Process XML format export (WordPress WXR or structured XML).
     */
    protected function processXml(string $filePath, string $xmlContent, bool $dryRun): void
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);

        if (! $dom->load($filePath, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
            // Try loading from memory
            if (! $dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                libxml_clear_errors();
                throw new \RuntimeException('Failed to parse XML file.');
            }
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('wp', 'http://wordpress.org/export/1.2/');
        $xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        // Process standard WordPress WXR <item> elements
        $items = $dom->getElementsByTagName('item');
        if ($items->length > 0) {
            foreach ($items as $item) {
                $this->processXmlItem($item, $xpath, $dryRun);
            }
        }

        // Also check for custom XML elements: <pages>, <meetings>, <menu_items>, <redirects>
        $customPages = $dom->getElementsByTagName('page');
        foreach ($customPages as $pageNode) {
            $this->processCustomPageNode($pageNode, $dryRun);
        }

        $customMeetings = $dom->getElementsByTagName('meeting');
        foreach ($customMeetings as $meetingNode) {
            $this->processCustomMeetingNode($meetingNode, $dryRun);
        }

        $customMenus = $dom->getElementsByTagName('menu_item');
        foreach ($customMenus as $menuNode) {
            $this->processCustomMenuNode($menuNode, $dryRun);
        }

        $customRedirects = $dom->getElementsByTagName('redirect');
        foreach ($customRedirects as $redirectNode) {
            $this->processCustomRedirectNode($redirectNode, $dryRun);
        }
    }

    /**
     * Process standard WordPress WXR <item> node.
     */
    protected function processXmlItem(DOMElement $item, DOMXPath $xpath, bool $dryRun): void
    {
        $title = $this->getNodeValue($item, 'title');
        $link = $this->getNodeValue($item, 'link');
        $postType = $this->getXpathValue($xpath, 'wp:post_type', $item) ?: $this->getNodeValue($item, 'post_type') ?: 'page';
        $postId = $this->getXpathValue($xpath, 'wp:post_id', $item) ?: $this->getNodeValue($item, 'post_id');
        $postName = $this->getXpathValue($xpath, 'wp:post_name', $item) ?: $this->getNodeValue($item, 'post_name');
        $encodedContent = $this->getXpathValue($xpath, 'content:encoded', $item) ?: $this->getNodeValue($item, 'content');

        // Extract postmeta
        $meta = [];
        $metaNodes = $xpath->query('.//wp:postmeta', $item);
        if ($metaNodes) {
            foreach ($metaNodes as $metaNode) {
                $k = $this->getXpathValue($xpath, 'wp:meta_key', $metaNode);
                $v = $this->getXpathValue($xpath, 'wp:meta_value', $metaNode);
                if ($k !== null) {
                    $meta[$k] = $v;
                }
            }
        }

        if ($postType === 'meeting' || $postType === 'schedule') {
            $this->importMeeting([
                'title' => $title,
                'link' => $link,
                'day_of_week' => $meta['day_of_week'] ?? $meta['_meeting_day'] ?? null,
                'time' => $meta['time'] ?? $meta['_meeting_time'] ?? null,
                'venue' => $meta['venue'] ?? $meta['_meeting_venue'] ?? null,
                'city' => $meta['city'] ?? $meta['_meeting_city'] ?? null,
                'notes' => $encodedContent ?: ($meta['notes'] ?? null),
                'group_name' => $meta['group_name'] ?? $meta['_group_name'] ?? null,
                'id' => $postId,
            ], $dryRun);
        } elseif ($postType === 'nav_menu_item' || $postType === 'menu_item') {
            $url = $meta['_menu_item_url'] ?? $link;
            $this->importMenuItem([
                'title' => $title,
                'url' => $url,
                'target_url' => $meta['target_url'] ?? null,
            ], $dryRun);
        } else {
            $this->importPage([
                'id' => $postId,
                'title' => $title,
                'slug' => $postName,
                'link' => $link,
                'content' => $encodedContent,
                'target_url' => $meta['target_url'] ?? null,
            ], $dryRun);
        }
    }

    /**
     * Process custom <page> XML node.
     */
    protected function processCustomPageNode(DOMElement $node, bool $dryRun): void
    {
        $this->importPage([
            'id' => $this->getNodeValue($node, 'id'),
            'title' => $this->getNodeValue($node, 'title'),
            'title_ar' => $this->getNodeValue($node, 'title_ar'),
            'title_en' => $this->getNodeValue($node, 'title_en'),
            'slug' => $this->getNodeValue($node, 'slug'),
            'link' => $this->getNodeValue($node, 'link') ?: $this->getNodeValue($node, 'url'),
            'content' => $this->getNodeValue($node, 'content') ?: $this->getNodeValue($node, 'body'),
            'body_ar' => $this->getNodeValue($node, 'body_ar'),
            'body_en' => $this->getNodeValue($node, 'body_en'),
            'target_url' => $this->getNodeValue($node, 'target_url'),
        ], $dryRun);
    }

    /**
     * Process custom <meeting> XML node.
     */
    protected function processCustomMeetingNode(DOMElement $node, bool $dryRun): void
    {
        $this->importMeeting([
            'id' => $this->getNodeValue($node, 'id'),
            'title' => $this->getNodeValue($node, 'title'),
            'day_of_week' => $this->getNodeValue($node, 'day_of_week') ?: $this->getNodeValue($node, 'day'),
            'time' => $this->getNodeValue($node, 'time'),
            'venue' => $this->getNodeValue($node, 'venue'),
            'city' => $this->getNodeValue($node, 'city'),
            'notes' => $this->getNodeValue($node, 'notes') ?: $this->getNodeValue($node, 'content'),
            'group_name' => $this->getNodeValue($node, 'group_name') ?: $this->getNodeValue($node, 'group'),
            'link' => $this->getNodeValue($node, 'link') ?: $this->getNodeValue($node, 'url'),
        ], $dryRun);
    }

    /**
     * Process custom <menu_item> XML node.
     */
    protected function processCustomMenuNode(DOMElement $node, bool $dryRun): void
    {
        $this->importMenuItem([
            'title' => $this->getNodeValue($node, 'title') ?: $this->getNodeValue($node, 'label'),
            'url' => $this->getNodeValue($node, 'url') ?: $this->getNodeValue($node, 'link'),
            'target_url' => $this->getNodeValue($node, 'target_url'),
        ], $dryRun);
    }

    /**
     * Process custom <redirect> XML node.
     */
    protected function processCustomRedirectNode(DOMElement $node, bool $dryRun): void
    {
        $this->importRedirect([
            'source_path' => $this->getNodeValue($node, 'source_path') ?: $this->getNodeValue($node, 'source') ?: $this->getNodeValue($node, 'from'),
            'target_url' => $this->getNodeValue($node, 'target_url') ?: $this->getNodeValue($node, 'target') ?: $this->getNodeValue($node, 'to'),
        ], $dryRun);
    }

    /**
     * Import a Page and map its redirects.
     *
     * @param  array<string, mixed>  $data
     */
    protected function importPage(array $data, bool $dryRun): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        $titleAr = trim((string) ($data['title_ar'] ?? ''));
        $titleEn = trim((string) ($data['title_en'] ?? ''));

        if ($titleAr === '' && $titleEn === '') {
            if ($this->containsArabic($title)) {
                $titleAr = $title;
                $titleEn = $title;
            } else {
                $titleEn = $title;
                $titleAr = $title !== '' ? $title : 'صفحة';
            }
        } elseif ($titleAr === '') {
            $titleAr = $titleEn;
        } elseif ($titleEn === '') {
            $titleEn = $titleAr;
        }

        $rawBody = (string) ($data['content'] ?? $data['body'] ?? '');
        $bodyAr = (string) ($data['body_ar'] ?? '');
        $bodyEn = (string) ($data['body_en'] ?? '');

        if ($bodyAr === '' && $bodyEn === '') {
            $sanitized = $this->sanitizeHtml($rawBody);
            $bodyAr = $sanitized;
            $bodyEn = $sanitized;
        } else {
            $bodyAr = $this->sanitizeHtml($bodyAr !== '' ? $bodyAr : $rawBody);
            $bodyEn = $this->sanitizeHtml($bodyEn !== '' ? $bodyEn : $rawBody);
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($titleEn ?: $titleAr);
        }
        if ($slug === '') {
            $slug = 'page-'.($data['id'] ?? uniqid());
        }

        $targetUrl = (string) ($data['target_url'] ?? '');
        if ($targetUrl === '') {
            // Map common slugs to established routes
            $targetUrl = match ($slug) {
                'home', 'home-2', 'front-page' => '/ar',
                'who-we-are', 'about', 'about-us' => '/ar/who-we-are',
                'privacy', 'privacy-policy' => '/ar/privacy',
                'meetings', 'meetings-list', 'find-meetings' => '/ar/meetings',
                default => '/ar/'.$slug,
            };
        }

        if (! $dryRun) {
            Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'body_ar' => $bodyAr,
                    'body_en' => $bodyEn,
                    'is_published' => true,
                    'meta_description_ar' => $data['meta_description_ar'] ?? null,
                    'meta_description_en' => $data['meta_description_en'] ?? null,
                ]
            );
        }

        // Register historical URL redirects
        $legacyUrls = [];
        if (! empty($data['link'])) {
            $legacyUrls[] = (string) $data['link'];
        }
        if (! empty($data['url'])) {
            $legacyUrls[] = (string) $data['url'];
        }
        if (! empty($data['id'])) {
            $legacyUrls[] = '/?p='.$data['id'];
            $legacyUrls[] = '/?page_id='.$data['id'];
        }
        if (! empty($data['slug'])) {
            $legacyUrls[] = '/'.$data['slug'];
            $legacyUrls[] = '/en/'.$data['slug'];
        }

        foreach (array_unique($legacyUrls) as $legacyUrl) {
            $this->addRedirect($legacyUrl, $targetUrl, $dryRun);
        }
    }

    /**
     * Import a Meeting and map its redirects.
     *
     * @param  array<string, mixed>  $data
     */
    protected function importMeeting(array $data, bool $dryRun): void
    {
        $title = trim((string) ($data['title'] ?? 'Meeting'));
        $dayOfWeek = $data['day_of_week'] ?? null;
        $time = $data['time'] ?? null;
        $venue = $data['venue'] ?? null;
        $city = $data['city'] ?? null;
        $notes = $this->sanitizeHtml((string) ($data['notes'] ?? ''));

        $groupName = (string) ($data['group_name'] ?? $data['group'] ?? '');
        $groupId = $data['group_id'] ?? null;

        if (! $dryRun) {
            $group = null;
            if ($groupId) {
                $group = Group::find($groupId);
            }
            if (! $group && $groupName !== '') {
                $group = Group::firstOrCreate(
                    ['slug' => Str::slug($groupName)],
                    ['name_en' => $groupName, 'name_ar' => $groupName]
                );
            }
            if (! $group) {
                $group = Group::first() ?? Group::create([
                    'name_en' => 'General Cairo Group',
                    'name_ar' => 'مجموعة القاهرة العامة',
                    'slug' => 'general-cairo-group',
                ]);
            }

            Meeting::updateOrCreate(
                [
                    'group_id' => $group->id,
                    'title' => $title,
                ],
                [
                    'day_of_week' => $dayOfWeek,
                    'time' => $time,
                    'venue' => $venue,
                    'city' => $city,
                    'notes' => $notes,
                ]
            );
        }

        // Register legacy meeting redirects
        $targetUrl = '/ar/meetings';
        $legacyUrls = [];
        if (! empty($data['link'])) {
            $legacyUrls[] = (string) $data['link'];
        }
        if (! empty($data['url'])) {
            $legacyUrls[] = (string) $data['url'];
        }
        if (! empty($data['id'])) {
            $legacyUrls[] = '/?p='.$data['id'];
        }

        foreach (array_unique($legacyUrls) as $legacyUrl) {
            $this->addRedirect($legacyUrl, $targetUrl, $dryRun);
        }
    }

    /**
     * Import a Menu Item as a redirect mapping.
     *
     * @param  array<string, mixed>  $data
     */
    protected function importMenuItem(array $data, bool $dryRun): void
    {
        $url = (string) ($data['url'] ?? $data['link'] ?? '');
        if ($url === '') {
            return;
        }

        $targetUrl = (string) ($data['target_url'] ?? '');
        if ($targetUrl === '') {
            $normalized = $this->normalizeSourcePath($url);
            $slug = trim((string) parse_url($url, PHP_URL_PATH), '/');

            $targetUrl = match ($slug) {
                'home', 'home-2', 'en/home-2' => '/en',
                'who-we-are' => '/ar/who-we-are',
                'privacy' => '/ar/privacy',
                'meetings', 'meetings-list' => '/ar/meetings',
                default => str_starts_with($slug, 'en/') ? '/'.$slug : '/ar/'.$slug,
            };
        }

        $this->addRedirect($url, $targetUrl, $dryRun);
    }

    /**
     * Import explicit redirect record.
     *
     * @param  array<string, mixed>  $data
     */
    protected function importRedirect(array $data, bool $dryRun): void
    {
        $source = (string) ($data['source_path'] ?? $data['source'] ?? $data['from'] ?? '');
        $target = (string) ($data['target_url'] ?? $data['target'] ?? $data['to'] ?? '');

        if ($source !== '' && $target !== '') {
            $this->addRedirect($source, $target, $dryRun);
        }
    }

    /**
     * Safely add a redirect after strict malicious domain validation.
     */
    protected function addRedirect(string $source, string $target, bool $dryRun): bool
    {
        if ($this->isMaliciousUrl($source)) {
            $this->warn("Skipping malicious legacy source URL: {$source}");

            return false;
        }

        if ($this->isMaliciousUrl($target)) {
            $this->warn("Skipping malicious legacy target URL: {$target}");

            return false;
        }

        $sourcePath = $this->normalizeSourcePath($source);
        if ($sourcePath === null || $sourcePath === '') {
            return false;
        }

        $targetUrl = $this->normalizeTargetUrl($target);
        if ($targetUrl === '' || $targetUrl === $sourcePath) {
            return false;
        }

        if (! $dryRun) {
            Redirect::updateOrCreate(
                ['source_path' => $sourcePath],
                ['target_url' => $targetUrl, 'hit_count' => 0]
            );
        }

        return true;
    }

    /**
     * Normalize legacy source URL to relative path with query.
     */
    public function normalizeSourcePath(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false) {
            return '/'.ltrim($trimmed, '/');
        }

        $path = $parsed['path'] ?? '';
        if ($path === '') {
            $path = '/';
        } elseif (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        if (! empty($parsed['query'])) {
            $path .= '?'.$parsed['query'];
        }

        return $path;
    }

    /**
     * Normalize target URL.
     */
    protected function normalizeTargetUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        // If relative path, ensure leading slash
        if (! str_starts_with($trimmed, 'http://') && ! str_starts_with($trimmed, 'https://') && ! str_starts_with($trimmed, '/')) {
            return '/'.$trimmed;
        }

        return $trimmed;
    }

    /**
     * Strictly sanitize HTML content.
     */
    public function sanitizeHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove null bytes
        $clean = str_replace(chr(0), '', $html);

        // 1. Strip <script> tags and all contents
        $clean = preg_replace('/<\s*script\b[^>]*>(.*?)<\s*\/\s*script\s*>/is', '', $clean);
        $clean = preg_replace('/<\s*script\b[^>]*\/?>/is', '', $clean);

        // 2. Strip <iframe> tags and all contents
        $clean = preg_replace('/<\s*iframe\b[^>]*>(.*?)<\s*\/\s*iframe\s*>/is', '', $clean);
        $clean = preg_replace('/<\s*iframe\b[^>]*\/?>/is', '', $clean);

        // 3. Strip <style> tags and all contents
        $clean = preg_replace('/<\s*style\b[^>]*>(.*?)<\s*\/\s*style\s*>/is', '', $clean);

        // 4. Strip <link> tags (external CSS, stylesheets)
        $clean = preg_replace('/<\s*link\b[^>]*\/?>/is', '', $clean);

        // 5. Strip object, embed, form
        $clean = preg_replace('/<\s*(object|embed|form)\b[^>]*>(.*?)<\s*\/\s*\1\s*>/is', '', $clean);
        $clean = preg_replace('/<\s*(object|embed)\b[^>]*\/?>/is', '', $clean);

        // 6. Strip injected backlinks (gambling, VPN, malicious domains)
        $clean = preg_replace_callback('/<\s*a\b([^>]*)>(.*?)<\s*\/\s*a\s*>/is', function ($matches) {
            $attrs = $matches[1];
            $inner = $matches[2];

            $href = '';
            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $hrefMatch)) {
                $href = $hrefMatch[1];
            }

            if ($this->isMaliciousUrl($href) || $this->containsMaliciousKeywords($inner) || $this->containsMaliciousKeywords($attrs)) {
                return ''; // Eliminate entire malicious backlink and spam text
            }

            $cleanHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

            return $cleanHref !== '' ? '<a href="'.$cleanHref.'">'.$inner.'</a>' : $inner;
        }, $clean);

        // Also clean self-closing <a> tags
        $clean = preg_replace_callback('/<\s*a\b([^>]*)\/\s*>/is', function ($matches) {
            $attrs = $matches[1];
            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $attrs, $hrefMatch)) {
                if ($this->isMaliciousUrl($hrefMatch[1])) {
                    return '';
                }
            }

            return '';
        }, $clean);

        // 7. Strip inline event handlers (onclick, onload, onerror, etc.)
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

        // 8. Strip inline style attributes
        $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

        // 9. Strip any injected raw URLs referencing malicious domains
        $clean = preg_replace('/\bhttps?:\/\/[^\s"\'>]*conde-dev\.us[^\s"\'>]*/i', '', $clean);
        $clean = preg_replace('/\bhttps?:\/\/[^\s"\'>]*(slot|gacor|judi|casino|poker|betting|sbobet)[^\s"\'>]*/i', '', $clean);

        // 10. Run strip_tags allowlisting standard clean tags
        $clean = strip_tags($clean, ['p', 'b', 'i', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'a', 'br']);

        return trim($clean);
    }

    /**
     * Validate whether a URL points to known malicious domains or schemes.
     */
    public function isMaliciousUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $lower = strtolower(trim($url));

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
            return true;
        }

        return $this->containsMaliciousKeywords($lower);
    }

    /**
     * Check if a string contains known malicious keywords or domains.
     */
    public function containsMaliciousKeywords(string $text): bool
    {
        $lower = strtolower($text);

        foreach ($this->maliciousKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a string contains Arabic characters.
     */
    protected function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * Helper to get node value safely.
     */
    protected function getNodeValue(DOMElement $element, string $tagName): ?string
    {
        $nodes = $element->getElementsByTagName($tagName);
        if ($nodes->length > 0 && $nodes->item(0)) {
            return trim($nodes->item(0)->nodeValue);
        }

        return null;
    }

    /**
     * Helper to get xpath value safely.
     */
    protected function getXpathValue(DOMXPath $xpath, string $expression, ?\DOMNode $context = null): ?string
    {
        $nodes = $xpath->query($expression, $context);
        if ($nodes && $nodes->length > 0 && $nodes->item(0)) {
            return trim($nodes->item(0)->nodeValue);
        }

        return null;
    }
}
