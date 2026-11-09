<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;

class ImportWordPressDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-wordpress
                            {--wp-path= : Absolute path to WordPress root directory (default: /var/www/aaegypt)}
                            {--fresh : Clear existing meetings, groups, pages and documents before import}
                            {--dry-run : Simulate the import without persisting changes}
                            {--no-media : Skip copying media files to public storage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import meetings, locations, groups, pages and media from WordPress aaegypt database';

    /**
     * Known spam/malicious keywords to skip.
     *
     * @var array<int, string>
     */
    protected array $spamKeywords = [
        'slot gacor',
        'slot-gacor',
        'slotgacor',
        'judi',
        'casino',
        'togel',
        'sbobet',
        'rahasia keuntungan',
        'bonus game',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $wpPath = $this->option('wp-path') ?: (file_exists('/var/www/aaegypt') ? '/var/www/aaegypt' : base_path('../aaegypt'));
        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $copyMedia = ! (bool) $this->option('no-media');

        if (! file_exists($wpPath) || ! file_exists($wpPath.'/wp-config.php')) {
            $this->error("WordPress directory or wp-config.php not found at: {$wpPath}");

            return self::FAILURE;
        }

        $this->info("Connecting to WordPress at: {$wpPath}");
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No database changes or file copies will occur.');
        }

        $dbConfig = $this->parseWpConfig($wpPath.'/wp-config.php');
        if (empty($dbConfig['db_name']) || empty($dbConfig['db_user'])) {
            $this->error('Failed to parse database configuration from wp-config.php.');

            return self::FAILURE;
        }

        try {
            $pdo = new PDO(
                "mysql:host={$dbConfig['db_host']};dbname={$dbConfig['db_name']};charset=utf8mb4",
                $dbConfig['db_user'],
                $dbConfig['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            $this->error("Failed to connect to WordPress MySQL database: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($fresh && ! $dryRun) {
            $this->warn('Clearing existing documents, meetings, groups, and pages...');
            Document::query()->delete();
            Meeting::query()->delete();
            Group::query()->delete();
            Page::query()->delete();
            $this->info('Existing data cleared.');
        }

        $prefix = $dbConfig['table_prefix'];

        $this->importGroupsAndMeetings($pdo, $prefix, $dryRun);
        $this->importPages($pdo, $prefix, $dryRun);

        if ($copyMedia) {
            $this->importMediaAndDocuments($pdo, $prefix, $wpPath, $dryRun);
        }

        $this->info('WordPress data import completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Parse WordPress database connection details from wp-config.php.
     *
     * @return array{db_name: string, db_user: string, db_password: string, db_host: string, table_prefix: string}
     */
    protected function parseWpConfig(string $configPath): array
    {
        $content = file_get_contents($configPath) ?: '';

        preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mName);
        preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mUser);
        preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mPass);
        preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mHost);
        preg_match('/\$table_prefix\s*=\s*[\'"](.*?)[\'"]/', $content, $mPrefix);

        $host = $mHost[1] ?? 'localhost';
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        return [
            'db_name' => $mName[1] ?? '',
            'db_user' => $mUser[1] ?? '',
            'db_password' => $mPass[1] ?? '',
            'db_host' => $host,
            'table_prefix' => $mPrefix[1] ?? 'wp_',
        ];
    }

    /**
     * Import groups and meetings from WordPress TSML tables.
     */
    protected function importGroupsAndMeetings(PDO $pdo, string $prefix, bool $dryRun): void
    {
        $this->info('Importing groups and meetings...');

        $stmt = $pdo->prepare("SELECT p.ID, p.post_title, p.post_content, p.post_parent FROM {$prefix}posts p WHERE p.post_type = 'tsml_meeting' AND p.post_status = 'publish' ORDER BY p.ID");
        $stmt->execute();
        $wpMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupDefinitions = [
            'zayed' => [
                'name_en' => 'AA Zayed Group',
                'name_ar' => 'مجموعة زايد',
                'slug' => 'aa-zayed-group',
            ],
            'keep_coming_back' => [
                'name_en' => 'Keep Coming Back Group',
                'name_ar' => 'مجموعة استمر في الحضور',
                'slug' => 'keep-coming-back-group',
            ],
            'alresala' => [
                'name_en' => 'AlResala Group',
                'name_ar' => 'مجموعة الرسالة',
                'slug' => 'alresala-group',
            ],
            'gouna' => [
                'name_en' => 'El Gouna Group',
                'name_ar' => 'مجموعة الجونة',
                'slug' => 'el-gouna-group',
            ],
            'grateful' => [
                'name_en' => 'Grateful Group Maadi',
                'name_ar' => 'مجموعة الامتنان بالمعادي',
                'slug' => 'grateful-group-maadi',
            ],
            'phones' => [
                'name_en' => 'Phones in a Basket',
                'name_ar' => 'مجموعة الهواتف في السلة',
                'slug' => 'phones-in-a-basket',
            ],
            'hq' => [
                'name_en' => 'HQ Space Maadi Group',
                'name_ar' => 'مجموعة إتش كيو سبيس المعادي',
                'slug' => 'hq-space-maadi-group',
            ],
            'zoom' => [
                'name_en' => 'Arabic Zoom Online Group',
                'name_ar' => 'مجموعة زووم الإلكترونية',
                'slug' => 'arabic-zoom-online-group',
            ],
            'russian' => [
                'name_en' => 'Russian Meeting Group',
                'name_ar' => 'المجموعة الروسية بالمعادي',
                'slug' => 'russian-meeting-group',
            ],
        ];

        /** @var array<string, Group> $createdGroups */
        $createdGroups = [];

        foreach ($groupDefinitions as $key => $gDef) {
            if ($dryRun) {
                $this->line(" [Dry Run] Would create group: {$gDef['name_en']} / {$gDef['name_ar']}");

                continue;
            }

            $group = Group::firstOrCreate(
                ['slug' => $gDef['slug']],
                ['name_en' => $gDef['name_en'], 'name_ar' => $gDef['name_ar']]
            );
            $createdGroups[$key] = $group;
        }

        $importedMeetingsCount = 0;

        foreach ($wpMeetings as $m) {
            $postId = (int) $m['ID'];
            $postTitle = trim((string) $m['post_title']);

            // Fetch postmeta for this meeting
            $pmStmt = $pdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
            $pmStmt->execute([$postId]);
            $postmeta = $pmStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Fetch parent location
            $locId = (int) $m['post_parent'];
            $loc = [];
            $locMeta = [];
            if ($locId > 0) {
                $locStmt = $pdo->prepare("SELECT ID, post_title, post_content FROM {$prefix}posts WHERE ID = ?");
                $locStmt->execute([$locId]);
                $loc = $locStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $lmStmt = $pdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
                $lmStmt->execute([$locId]);
                $locMeta = $lmStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            }

            // Determine Group Key
            $groupKey = 'keep_coming_back';
            $lowerTitle = strtolower($postTitle);
            if (str_contains($lowerTitle, 'zayed') || str_contains($postTitle, 'زايد')) {
                $groupKey = 'zayed';
            } elseif (str_contains($lowerTitle, 'resala') || str_contains($postTitle, 'الرسالة')) {
                $groupKey = 'alresala';
            } elseif (str_contains($lowerTitle, 'gouna') || str_contains($lowerTitle, 'el gouna')) {
                $groupKey = 'gouna';
            } elseif (str_contains($lowerTitle, 'grateful')) {
                $groupKey = 'grateful';
            } elseif (str_contains($lowerTitle, 'phones')) {
                $groupKey = 'phones';
            } elseif (str_contains($lowerTitle, 'hq space')) {
                $groupKey = 'hq';
            } elseif (str_contains($lowerTitle, 'zoom') || str_contains($postTitle, 'زووم')) {
                $groupKey = 'zoom';
            } elseif (str_contains($lowerTitle, 'russian')) {
                $groupKey = 'russian';
            }

            // Parse Types
            $rawTypes = $postmeta['types'] ?? '';
            $types = ! empty($rawTypes) ? @unserialize($rawTypes) : [];
            if (! is_array($types)) {
                $types = [];
            }

            // Determine Online / In Person
            $isOnline = in_array('ONL', $types, true) || in_array('O', $types, true) || ! empty($postmeta['conference_url']) || str_contains($lowerTitle, 'zoom');
            $type = $isOnline ? 'online' : 'in_person';

            // Determine Language
            $lang = 'en';
            if (in_array('AR', $types, true) || preg_match('/[\x{0600}-\x{06FF}]/u', $postTitle)) {
                $lang = 'ar';
            }

            // Location information
            $venue = $loc['post_title'] ?? '';
            if (str_starts_with($venue, 'http')) {
                $venue = 'HQ Space Maadi';
            }

            $address = $locMeta['formatted_address'] ?? '';
            $lat = ! empty($locMeta['latitude']) ? (float) $locMeta['latitude'] : null;
            $lng = ! empty($locMeta['longitude']) ? (float) $locMeta['longitude'] : null;

            // Determine City
            $city = 'Cairo';
            $locLower = strtolower($venue.' '.$address);
            if (str_contains($locLower, 'gouna') || str_contains($locLower, 'hurghada')) {
                $city = 'El Gouna';
            } elseif (str_contains($locLower, 'zayed') || str_contains($locLower, 'giza')) {
                $city = 'Giza';
            } elseif ($isOnline) {
                $city = 'Online';
            }

            // Parse Day of Week & Times
            $dayOfWeek = isset($postmeta['day']) && is_numeric($postmeta['day']) ? (int) $postmeta['day'] : null;
            $startTime = ! empty($postmeta['time']) ? substr((string) $postmeta['time'], 0, 5) : null;
            $endTime = ! empty($postmeta['end_time']) ? substr((string) $postmeta['end_time'], 0, 5) : null;

            // Clean Notes
            $notes = $this->cleanWordPressContent((string) $m['post_content']);
            $locNotes = $this->cleanWordPressContent((string) ($loc['post_content'] ?? ''));
            if (! empty($locNotes) && ! str_contains($notes, $locNotes)) {
                $notes = trim($notes."\n\n".$locNotes);
            }

            $onlineLink = $postmeta['conference_url'] ?? null;

            if ($dryRun) {
                $this->line(" [Dry Run] Would create meeting: '{$postTitle}' ({$city}, day: {$dayOfWeek}, time: {$startTime}-{$endTime})");
                $importedMeetingsCount++;

                continue;
            }

            $groupId = $createdGroups[$groupKey]->id ?? 1;

            Meeting::updateOrCreate(
                [
                    'name' => $postTitle,
                    'group_id' => $groupId,
                ],
                [
                    'title' => $postTitle,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'time' => $startTime,
                    'venue' => $venue,
                    'venue_address' => $address,
                    'city' => $city,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'type' => $type,
                    'online_link' => $onlineLink,
                    'language' => $lang,
                    'notes' => $notes ?: null,
                    'is_active' => true,
                ]
            );

            $importedMeetingsCount++;
        }

        $this->info("Imported {$importedMeetingsCount} meetings into ".count($groupDefinitions).' groups.');
    }

    /**
     * Import static published pages (Who we are, Reach out, Privacy policy) with Arabic translations.
     */
    protected function importPages(PDO $pdo, string $prefix, bool $dryRun): void
    {
        $this->info('Importing static pages...');

        $stmt = $pdo->prepare("SELECT ID, post_title, post_name, post_content FROM {$prefix}posts WHERE post_type = 'page' AND post_status = 'publish' ORDER BY ID");
        $stmt->execute();
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $staticPagesConfig = [
            'who-we-are' => [
                'title_en' => 'Who We Are',
                'title_ar' => 'من نحن',
                'body_ar' => '<p>مدمنو الخمر المجهولون هي زمالة من الرجال والنساء الذين يتقاسمون خبراتهم وقوتهم وأملهم مع بعضهم البعض حتى يتمكنوا من حل مشكلتهم المشتركة ومساعدة الآخرين على التعافي من إدمان الخمر.</p>'."\n".
                             '<p>الشرط الوحيد للعضوية هو الرغبة في التوقف عن الشرب. لا توجد رسوم أو مستحقات لعضوية مدمني الخمر المجهولين؛ نحن نعتمد على أنفسنا من خلال مساهماتنا الذاتية.</p>'."\n".
                             '<p>إن زمالة مدمني الخمر المجهولين لا ترتبط بأي طائفة، أو مذهب، أو سياسة، أو منظمة أو مؤسسة؛ ولا ترغب في الخوض في أي جدل؛ ولا تؤيد ولا تعارض أي قضايا. هدفنا الأساسي هو أن نبقى ممتنعين عن الشرب ومساعدة مدمني الخمر الآخرين على تحقيق الامتناع.</p>',
            ],
            'contact-us' => [
                'title_en' => 'Reach Out',
                'title_ar' => 'تواصل معنا',
                'body_ar' => '<p><strong>البريد الإلكتروني</strong><br><a href="mailto:cairoaajust42day@gmail.com">cairoaajust42day@gmail.com</a><br><a href="mailto:info@aaegypt.org">info@aaegypt.org</a></p>'."\n".
                             '<p><strong>القاهرة</strong><br>+17815307250</p>'."\n".
                             '<p><strong>الجونة</strong><br><a href="tel:201282281394">+201282281394</a></p>'."\n".
                             '<p><strong>الغردقة</strong><br><a href="tel:201110972072">+201110972072</a></p>',
            ],
            'privacy-policy' => [
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'body_ar' => '<p>خصوصية زوار وأعضاء زمالة مدمني الخمر المجهولين في مصر ذات أهمية قصوى بالنسبة لنا. تلتزم زمالتنا بمبدأ إغفال الاسم والسرية التامة وفقاً للتقاليد الإثني عشر لمدمني الخمر المجهولين.</p>',
            ],
        ];

        $importedPagesCount = 0;

        foreach ($pages as $p) {
            $slug = trim((string) $p['post_name']);
            $title = trim((string) $p['post_title']);
            $content = (string) $p['post_content'];

            // Skip spam or blank pages
            if ($this->isSpam($title, $content) || empty(trim(strip_tags($content)))) {
                continue;
            }

            // Match against static pages config
            $matchedKey = null;
            if (isset($staticPagesConfig[$slug])) {
                $matchedKey = $slug;
            } elseif (str_contains($slug, 'contact') || str_contains(strtolower($title), 'reach out')) {
                $matchedKey = 'contact-us';
            } elseif (str_contains($slug, 'who-we-are') || str_contains(strtolower($title), 'who we are')) {
                $matchedKey = 'who-we-are';
            } elseif (str_contains($slug, 'privacy') || str_contains(strtolower($title), 'privacy')) {
                $matchedKey = 'privacy-policy';
            }

            if (! $matchedKey) {
                continue;
            }

            $conf = $staticPagesConfig[$matchedKey];
            $cleanBodyEn = $this->cleanWordPressContent($content);

            if ($dryRun) {
                $this->line(" [Dry Run] Would create page: '{$conf['title_en']}' / '{$conf['title_ar']}' (slug: {$matchedKey})");
                $importedPagesCount++;

                continue;
            }

            Page::updateOrCreate(
                ['slug' => $matchedKey],
                [
                    'title_en' => $conf['title_en'],
                    'title_ar' => $conf['title_ar'],
                    'body_en' => $cleanBodyEn,
                    'body_ar' => $conf['body_ar'],
                    'is_published' => true,
                    'meta_description_en' => Str::limit(strip_tags($cleanBodyEn), 160),
                    'meta_description_ar' => Str::limit(strip_tags($conf['body_ar']), 160),
                ]
            );

            $importedPagesCount++;
        }

        $this->info("Imported {$importedPagesCount} static pages.");
    }

    /**
     * Copy legitimate media attachments and register documents.
     */
    protected function importMediaAndDocuments(PDO $pdo, string $prefix, string $wpPath, bool $dryRun): void
    {
        $this->info('Importing authentic media files and documents...');

        $stmt = $pdo->prepare("SELECT p.ID, p.post_title, p.post_mime_type, pm.meta_value as attached_file 
            FROM {$prefix}posts p 
            LEFT JOIN {$prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            WHERE p.post_type = 'attachment'");
        $stmt->execute();
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $uploadsDir = rtrim($wpPath, '/').'/wp-content/uploads';
        $destBaseDir = storage_path('app/public/media');

        if (! $dryRun && ! File::exists($destBaseDir)) {
            File::makeDirectory($destBaseDir, 0755, true);
        }

        $copiedCount = 0;
        $registeredDocsCount = 0;
        $defaultGroup = Group::first();

        foreach ($attachments as $a) {
            $attachedFile = $a['attached_file'] ?? '';
            if (empty($attachedFile)) {
                continue;
            }

            // Exclude backup or generator directories
            if (str_starts_with($attachedFile, 'backwpup') || str_starts_with($attachedFile, 'visualcomposer-assets')) {
                continue;
            }

            $sourcePath = $uploadsDir.'/'.$attachedFile;
            if (! File::exists($sourcePath)) {
                continue;
            }

            $destPath = $destBaseDir.'/'.$attachedFile;

            if ($dryRun) {
                $copiedCount++;
                if (str_ends_with(strtolower($attachedFile), '.pdf')) {
                    $registeredDocsCount++;
                }

                continue;
            }

            $destDir = dirname($destPath);
            if (! File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }

            File::copy($sourcePath, $destPath);
            $copiedCount++;

            // If this is the AA emblem or logo, ensure it is available in public/images
            $publicImagesDir = public_path('images');
            if (! File::exists($publicImagesDir)) {
                File::makeDirectory($publicImagesDir, 0755, true);
            }

            $basename = basename($attachedFile);
            if ($basename === 'aaEmblem.png') {
                File::copy($sourcePath, $publicImagesDir.'/emblem.png');
                File::copy($sourcePath, public_path('favicon.ico'));
            } elseif ($basename === 'AA_Logo.jpg') {
                File::copy($sourcePath, $publicImagesDir.'/logo.jpg');
            } elseif ($basename === 'alcoholics-anonymous.png') {
                File::copy($sourcePath, $publicImagesDir.'/logo.png');
            }

            // If it is a PDF meeting list document, register in documents table
            if (str_ends_with(strtolower($attachedFile), '.pdf') && $defaultGroup) {
                Document::updateOrCreate(
                    ['file_path' => 'media/'.$attachedFile],
                    [
                        'group_id' => $defaultGroup->id,
                        'title' => trim((string) $a['post_title']) ?: 'AA Egypt Meeting List',
                    ]
                );
                $registeredDocsCount++;
            }
        }

        $this->info("Copied {$copiedCount} media files and registered {$registeredDocsCount} documents.");
    }

    /**
     * Clean Gutenberg blocks and qTranslate tags from WordPress HTML content.
     */
    protected function cleanWordPressContent(string $rawContent): string
    {
        // Extract English portion if qTranslate tags exist
        if (preg_match('/\[:en\](.*?)(\[:[a-z0-9\-]+\]|\[:\]|$)/s', $rawContent, $match)) {
            $content = $match[1];
        } else {
            $content = preg_replace('/\[:[a-z0-9\-]*\]/', '', $rawContent);
        }

        // Remove WordPress Gutenberg block comments e.g. <!-- wp:paragraph ... -->
        $content = preg_replace('/<!--\s*\/?wp:[^>]*-->/i', '', (string) $content);

        // Normalize excess line breaks
        $content = preg_replace("/[\r\n]{3,}/", "\n\n", (string) $content);

        return trim((string) $content);
    }

    /**
     * Check if post contains known spam/malicious keywords.
     */
    protected function isSpam(string $title, string $content): bool
    {
        $haystack = strtolower($title.' '.$content);
        foreach ($this->spamKeywords as $keyword) {
            if (str_contains($haystack, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
