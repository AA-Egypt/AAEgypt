<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Page;
use App\Services\Search\ArabicNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SearchController extends Controller
{
    /**
     * Common bilingual Egyptian location synonyms.
     *
     * @var array<string, array<int, string>>
     */
    protected const LOCATION_SYNONYMS = [
        'cairo' => ['cairo', 'القاهرة', 'القاهره'],
        'القاهرة' => ['cairo', 'القاهرة', 'القاهره'],
        'القاهره' => ['cairo', 'القاهرة', 'القاهره'],
        'alexandria' => ['alexandria', 'الاسكندرية', 'الإسكندرية', 'اسكندرية'],
        'الاسكندرية' => ['alexandria', 'الاسكندرية', 'الإسكندرية', 'اسكندرية'],
        'الإسكندرية' => ['alexandria', 'الاسكندرية', 'الإسكندرية', 'اسكندرية'],
        'اسكندرية' => ['alexandria', 'الاسكندرية', 'الإسكندرية', 'اسكندرية'],
        'giza' => ['giza', 'الجيزة', 'الجيزه'],
        'الجيزة' => ['giza', 'الجيزة', 'الجيزه'],
        'الجيزه' => ['giza', 'الجيزة', 'الجيزه'],
        'maadi' => ['maadi', 'المعادي', 'معادي'],
        'المعادي' => ['maadi', 'المعادي', 'معادي'],
        'معادي' => ['maadi', 'المعادي', 'معادي'],
        'zamalek' => ['zamalek', 'الزمالك', 'زمالك'],
        'الزمالك' => ['zamalek', 'الزمالك', 'زمالك'],
        'زمالك' => ['zamalek', 'الزمالك', 'زمالك'],
        'heliopolis' => ['heliopolis', 'مصر الجديدة', 'مصر الجديده'],
        'مصر الجديدة' => ['heliopolis', 'مصر الجديدة', 'مصر الجديده'],
        'steps' => ['steps', 'خطوات', 'الخطوات'],
        'step' => ['step', 'خطوة', 'خطوه'],
        'خطوات' => ['steps', 'خطوات', 'الخطوات'],
        'الخطوات' => ['steps', 'خطوات', 'الخطوات'],
    ];

    /**
     * Execute search across meetings and content pages.
     */
    public function index(Request $request, string $locale): View
    {
        $startTime = microtime(true);

        App::setLocale($locale);

        $rawQuery = trim((string) $request->query('q', ''));
        $activeTab = (string) $request->query('tab', 'all');

        if ($rawQuery === '') {
            return view('public.search', [
                'locale' => $locale,
                'query' => '',
                'normalizedQuery' => '',
                'meetings' => collect(),
                'pages' => collect(),
                'totalCount' => 0,
                'activeTab' => $activeTab,
                'durationMs' => 0.0,
            ]);
        }

        $normalizedQuery = ArabicNormalizer::normalize($rawQuery);
        $searchTerms = $this->resolveSearchTerms($rawQuery, $normalizedQuery);

        // 1. Search Meetings
        $meetingQuery = Meeting::query()->with('group');
        $meetingQuery->where(function ($builder) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $builder->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('venue', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhere('search_vector', 'like', "%{$term}%")
                    ->orWhereHas('group', function ($g) use ($term) {
                        $g->where('name_en', 'like', "%{$term}%")
                            ->orWhere('name_ar', 'like', "%{$term}%");
                    });
            }
        });
        $meetings = $meetingQuery->limit(50)->get();

        // Attach highlighted fields to meetings
        $meetings->each(function (Meeting $meeting) use ($searchTerms, $locale) {
            $groupName = $locale === 'ar'
                ? ($meeting->group?->name_ar ?: $meeting->group?->name_en)
                : ($meeting->group?->name_en ?: $meeting->group?->name_ar);

            $meeting->display_title = $meeting->title ?: $groupName;
            $meeting->highlighted_title = $this->highlightText($meeting->display_title, $searchTerms);

            $details = array_filter([
                $groupName ? ($locale === 'ar' ? "المجموعة: {$groupName}" : "Group: {$groupName}") : null,
                $meeting->city ? ($locale === 'ar' ? "المدينة: {$meeting->city}" : "City: {$meeting->city}") : null,
                $meeting->venue ? ($locale === 'ar' ? "المكان: {$meeting->venue}" : "Venue: {$meeting->venue}") : null,
                $meeting->notes ? ($locale === 'ar' ? "ملاحظات: {$meeting->notes}" : "Notes: {$meeting->notes}") : null,
            ]);
            $meeting->highlighted_excerpt = $this->highlightText(implode(' — ', $details), $searchTerms, 220);
        });

        // 2. Search Published Pages
        $pageQuery = Page::query()->where('is_published', true);
        $pageQuery->where(function ($builder) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $builder->orWhere('title_ar', 'like', "%{$term}%")
                    ->orWhere('title_en', 'like', "%{$term}%")
                    ->orWhere('body_ar', 'like', "%{$term}%")
                    ->orWhere('body_en', 'like', "%{$term}%")
                    ->orWhere('search_vector', 'like', "%{$term}%");
            }
        });
        $pages = $pageQuery->limit(50)->get();

        // Attach highlighted fields to pages
        $pages->each(function (Page $page) use ($searchTerms, $locale) {
            $displayTitle = $locale === 'ar'
                ? ($page->title_ar ?: $page->title_en)
                : ($page->title_en ?: $page->title_ar);

            $bodyText = $locale === 'ar'
                ? ($page->body_ar ?: $page->body_en)
                : ($page->body_en ?: $page->body_ar);

            $page->display_title = $displayTitle;
            $page->highlighted_title = $this->highlightText($displayTitle, $searchTerms);
            $page->highlighted_excerpt = $this->highlightText((string) $bodyText, $searchTerms, 240);
        });

        $totalCount = $meetings->count() + $pages->count();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return view('public.search', [
            'locale' => $locale,
            'query' => $rawQuery,
            'normalizedQuery' => $normalizedQuery,
            'meetings' => $meetings,
            'pages' => $pages,
            'totalCount' => $totalCount,
            'activeTab' => $activeTab,
            'durationMs' => $durationMs,
        ]);
    }

    /**
     * Resolve all normalized search tokens, variants, and synonyms.
     *
     * @return array<int, string>
     */
    protected function resolveSearchTerms(string $rawQuery, string $normalizedQuery): array
    {
        $terms = [];

        if ($rawQuery !== '') {
            $terms[] = $rawQuery;
        }

        if ($normalizedQuery !== '' && $normalizedQuery !== $rawQuery) {
            $terms[] = $normalizedQuery;
        }

        $tokens = ArabicNormalizer::tokenize($rawQuery, true);
        foreach ($tokens as $token) {
            $terms[] = $token;
        }

        // Expand synonyms
        $lookupKeys = array_merge([$rawQuery, $normalizedQuery], $tokens);
        foreach ($lookupKeys as $key) {
            $lowerKey = mb_strtolower(trim($key), 'UTF-8');
            if (isset(self::LOCATION_SYNONYMS[$lowerKey])) {
                foreach (self::LOCATION_SYNONYMS[$lowerKey] as $synonym) {
                    $terms[] = $synonym;
                }
            }
        }

        // Filter and deduplicate
        $cleanTerms = [];
        foreach ($terms as $term) {
            $trimmed = trim($term);
            if ($trimmed !== '' && mb_strlen($trimmed, 'UTF-8') >= 2) {
                $cleanTerms[] = $trimmed;
            }
        }

        return array_values(array_unique($cleanTerms));
    }

    /**
     * Generate safe highlighted snippet excerpt for UI display.
     *
     * @param  array<int, string>  $terms
     */
    protected function highlightText(string $text, array $terms, int $maxLen = 160): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($clean === '') {
            return '';
        }

        $bestPos = null;
        $cleanLower = mb_strtolower($clean, 'UTF-8');

        foreach ($terms as $term) {
            $termLower = mb_strtolower($term, 'UTF-8');
            if (mb_strlen($termLower, 'UTF-8') < 2) {
                continue;
            }

            $pos = mb_strpos($cleanLower, $termLower, 0, 'UTF-8');
            if ($pos !== false) {
                if ($bestPos === null || $pos < $bestPos) {
                    $bestPos = $pos;
                }
            }
        }

        if ($bestPos === null) {
            $bestPos = 0;
        }

        $start = max(0, $bestPos - 40);
        $excerpt = mb_substr($clean, $start, $maxLen, 'UTF-8');
        if ($start > 0) {
            $excerpt = '... '.$excerpt;
        }
        if ($start + $maxLen < mb_strlen($clean, 'UTF-8')) {
            $excerpt .= ' ...';
        }

        // XSS safety: escape plain text first
        $escaped = e($excerpt);

        // Sort terms by length descending
        $sortedTerms = array_unique(array_filter($terms, fn ($t) => mb_strlen(trim($t), 'UTF-8') >= 2));
        usort($sortedTerms, fn ($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($sortedTerms as $term) {
            $pattern = $this->buildRegexPattern($term);
            if ($pattern !== '') {
                $escaped = (string) preg_replace_callback(
                    '/(<mark[^>]*>.*?<\/mark>)|('.$pattern.')/iu',
                    function (array $matches): string {
                        if (! empty($matches[1])) {
                            return $matches[1];
                        }

                        return '<mark class="bg-amber-200 text-amber-950 font-semibold px-0.5 rounded">'.$matches[2].'</mark>';
                    },
                    $escaped
                );
            }
        }

        return $escaped;
    }

    /**
     * Build regex pattern that matches English and Arabic spelling variations.
     */
    protected function buildRegexPattern(string $term): string
    {
        $escaped = e($term);

        // Check if string contains Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $escaped)) {
            $normalized = ArabicNormalizer::normalize($escaped);
            $stripped = ArabicNormalizer::stripDefiniteArticle($normalized);
            $target = $stripped !== '' ? $stripped : $normalized;

            $chars = preg_split('//u', $target, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false || empty($chars)) {
                return preg_quote($escaped, '/');
            }

            $patternParts = [];
            foreach ($chars as $char) {
                if (in_array($char, ['ا', 'أ', 'إ', 'آ', 'ٱ'], true)) {
                    $patternParts[] = '[اأإآٱ]';
                } elseif (in_array($char, ['ه', 'ة'], true)) {
                    $patternParts[] = '[هة]';
                } elseif (in_array($char, ['ي', 'ى'], true)) {
                    $patternParts[] = '[يى]';
                } elseif (in_array($char, ['و', 'ؤ'], true)) {
                    $patternParts[] = '[وؤ]';
                } elseif (in_array($char, ['ء', 'ئ'], true)) {
                    $patternParts[] = '[ءئ]';
                } else {
                    $patternParts[] = preg_quote($char, '/');
                }
            }

            $inner = implode('[\x{064B}-\x{0652}\x{0640}]*', $patternParts);

            return '(?:ال)?'.$inner;
        }

        return preg_quote($escaped, '/');
    }
}
