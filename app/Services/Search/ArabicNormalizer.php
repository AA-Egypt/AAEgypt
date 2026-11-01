<?php

namespace App\Services\Search;

class ArabicNormalizer
{
    /**
     * Regular expression range for Arabic diacritics / tashkeel:
     * Fatha, Damma, Kasra, Sukun, Shadda, and Tanween ([\x{064B}-\x{0652}]).
     */
    protected const TASHKEEL_REGEX = '/[\x{064B}-\x{0652}]/u';

    /**
     * Regular expression for Tatweel / Kashida (\x{0640}).
     */
    protected const TATWEEL_REGEX = '/\x{0640}/u';

    /**
     * Regular expression for Alef variants [أإآٱ] -> ا.
     */
    protected const ALEF_REGEX = '/[أإآٱ]/u';

    /**
     * Regular expression for Hamza variants [ؤئ] -> ء.
     */
    protected const HAMZA_REGEX = '/[ؤئ]/u';

    /**
     * Regular expression for Ta Marbuta ة -> ه.
     */
    protected const TA_MARBUTA_REGEX = '/ة/u';

    /**
     * Regular expression for Alif Maqsura ى -> ي.
     */
    protected const ALIF_MAQSURA_REGEX = '/ى/u';

    /**
     * Regular expression matching non-letters, non-numbers, and non-whitespace characters.
     */
    protected const PUNCTUATION_REGEX = '/[^\p{L}\p{N}\s]/u';

    /**
     * Normalize Arabic and mixed English text for full-text search.
     *
     * Rules applied:
     * 1. Strip all Arabic diacritics / tashkeel (Fatha, Damma, Kasra, Sukun, Shadda, Tanween).
     * 2. Strip Tatweel / Kashida.
     * 3. Normalize Alef variants ([أإآٱ] -> ا).
     * 4. Normalize Hamza variants ([ؤئ] -> ء).
     * 5. Normalize Ta Marbuta (ة -> ه).
     * 6. Normalize Alif Maqsura (ى -> ي).
     * 7. Normalize mixed English queries (lowercase, trim punctuation).
     */
    public static function normalize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        self::ensureUtf8Encoding();

        // 1. Strip all Arabic diacritics / tashkeel
        $text = (string) preg_replace(self::TASHKEEL_REGEX, '', $text);

        // 2. Strip Tatweel / Kashida
        $text = (string) preg_replace(self::TATWEEL_REGEX, '', $text);

        // 3. Normalize Alef variants: [أإآٱ] -> ا
        $text = (string) preg_replace(self::ALEF_REGEX, 'ا', $text);

        // 4. Normalize Hamza variants: [ؤئ] -> ء
        $text = (string) preg_replace(self::HAMZA_REGEX, 'ء', $text);

        // 5. Normalize Ta Marbuta: ة -> ه
        $text = (string) preg_replace(self::TA_MARBUTA_REGEX, 'ه', $text);

        // 6. Normalize Alif Maqsura: ى -> ي
        $text = (string) preg_replace(self::ALIF_MAQSURA_REGEX, 'ي', $text);

        // 7. Normalize mixed English queries (lowercase, trim punctuation)
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace(self::PUNCTUATION_REGEX, ' ', $text);

        // Collapse consecutive spaces and trim edges
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Tokenize text into normalized words for database full-text matching.
     *
     * @param  bool  $expandArticles  Whether to include stripped definite article ('ال') variants
     * @return array<int, string>
     */
    public static function tokenize(?string $text, bool $expandArticles = false): array
    {
        if ($text === null) {
            return [];
        }

        $normalized = self::normalize($text);

        if ($normalized === '') {
            return [];
        }

        self::ensureUtf8Encoding();

        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || empty($tokens)) {
            return [];
        }

        if (! $expandArticles) {
            return array_values(array_unique($tokens));
        }

        $expanded = [];

        foreach ($tokens as $token) {
            $expanded[] = $token;
            $stripped = self::stripDefiniteArticle($token);

            if ($stripped !== $token && $stripped !== '') {
                $expanded[] = $stripped;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Strip the Arabic definite article ('ال') from a word when appropriate.
     * Leaves words of length < 4 or protected words like 'الله' intact.
     */
    public static function stripDefiniteArticle(string $word): string
    {
        $normalized = self::normalize($word);

        if (
            str_starts_with($normalized, 'ال') &&
            mb_strlen($normalized, 'UTF-8') >= 4 &&
            $normalized !== 'الله'
        ) {
            return mb_substr($normalized, 2, null, 'UTF-8');
        }

        return $normalized;
    }

    /**
     * Determine if a search query matches target text across varied Arabic spelling patterns.
     */
    public static function matches(?string $query, ?string $target): bool
    {
        if ($query === null || $target === null) {
            return false;
        }

        $normalizedQuery = self::normalize($query);
        $normalizedTarget = self::normalize($target);

        if ($normalizedQuery === '' || $normalizedTarget === '') {
            return false;
        }

        // Direct normalized equality
        if ($normalizedQuery === $normalizedTarget) {
            return true;
        }

        // Direct substring containment
        if (str_contains($normalizedTarget, $normalizedQuery)) {
            return true;
        }

        // Query with stripped definite article contained in target
        $strippedQuery = self::stripDefiniteArticle($normalizedQuery);
        if ($strippedQuery !== '' && str_contains($normalizedTarget, $strippedQuery)) {
            return true;
        }

        // Target with stripped definite article contained in query or vice-versa
        $strippedTarget = self::stripDefiniteArticle($normalizedTarget);
        if ($strippedTarget !== '' && str_contains($normalizedQuery, $strippedTarget)) {
            return true;
        }

        // Token-level matching (all query tokens must match a target token)
        $queryTokens = self::tokenize($normalizedQuery);
        $targetTokens = self::tokenize($normalizedTarget, true);

        if (empty($queryTokens) || empty($targetTokens)) {
            return false;
        }

        foreach ($queryTokens as $qToken) {
            $qStripped = self::stripDefiniteArticle($qToken);
            $found = false;

            foreach ($targetTokens as $tToken) {
                if ($qToken === $tToken || $qStripped === $tToken || str_contains($tToken, $qStripped)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure UTF-8 regex and internal encoding safety.
     */
    protected static function ensureUtf8Encoding(): void
    {
        mb_regex_encoding('UTF-8');
        mb_internal_encoding('UTF-8');
    }
}
