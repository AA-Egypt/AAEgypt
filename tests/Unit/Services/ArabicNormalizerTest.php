<?php

namespace Tests\Unit\Services;

use App\Services\Search\ArabicNormalizer;
use PHPUnit\Framework\TestCase;

class ArabicNormalizerTest extends TestCase
{
    /**
     * Test Acceptance Criterion 1:
     * ArabicNormalizer::normalize('مَجْمُوعَة المَعَادِي') produces 'مجموعه المعادي'.
     */
    public function test_normalize_acceptance_criterion_example(): void
    {
        $input = 'مَجْمُوعَة المَعَادِي';
        $expected = 'مجموعه المعادي';

        $this->assertSame($expected, ArabicNormalizer::normalize($input));
    }

    /**
     * Test Acceptance Criterion 2:
     * Query 'معادي' matches 'المعادي'.
     */
    public function test_query_maadi_matches_el_maadi(): void
    {
        $this->assertTrue(ArabicNormalizer::matches('معادي', 'المعادي'));
        $this->assertTrue(ArabicNormalizer::matches('المعادي', 'معادي'));
        $this->assertTrue(ArabicNormalizer::matches('معادي', 'مَجْمُوعَة المَعَادِي'));

        // Also verify via tokenize with article expansion
        $tokens = ArabicNormalizer::tokenize('المعادي', true);
        $this->assertContains('معادي', $tokens);
        $this->assertContains('المعادي', $tokens);
    }

    /**
     * Test Acceptance Criterion 3:
     * Query 'احمد' matches 'أحمد'.
     */
    public function test_query_ahmed_matches_normalized_spelling(): void
    {
        $this->assertTrue(ArabicNormalizer::matches('احمد', 'أحمد'));
        $this->assertTrue(ArabicNormalizer::matches('أحمد', 'احمد'));
        $this->assertSame('احمد', ArabicNormalizer::normalize('أحمد'));
        $this->assertSame('احمد', ArabicNormalizer::normalize('احمد'));
    }

    /**
     * Test Rule 1: Strip all Arabic diacritics / tashkeel.
     * Fatha (\x064E), Damma (\x064F), Kasra (\x0650),
     * Sukun (\x0652), Shadda (\x0651), Tanween (\x064B, \x064C, \x064D).
     */
    public function test_rule_1_strips_tashkeel(): void
    {
        // Fatha, Damma, Kasra, Sukun, Shadda, Tanween
        $withTashkeel = 'بَيْتٌ كَبِيرٌ وَمُدَرِّسٌ فِي الفَصْلِ مَعَ الطُّلَّابِ';
        $normalized = ArabicNormalizer::normalize($withTashkeel);

        $this->assertSame('بيت كبير ومدرس في الفصل مع الطلاب', $normalized);
    }

    /**
     * Test Rule 2: Strip Tatweel / Kashida (\x{0640}).
     */
    public function test_rule_2_strips_tatweel_kashida(): void
    {
        $withTatweel = 'مـــــعـــــادي وأحـــــمـــــد';
        $normalized = ArabicNormalizer::normalize($withTatweel);

        $this->assertSame('معادي واحمد', $normalized);
    }

    /**
     * Test Rule 3: Normalize Alef variants: [أإآٱ] -> ا.
     */
    public function test_rule_3_normalizes_alef_variants(): void
    {
        $this->assertSame('احمد', ArabicNormalizer::normalize('أحمد'));
        $this->assertSame('ابراهيم', ArabicNormalizer::normalize('إبراهيم'));
        $this->assertSame('ادم', ArabicNormalizer::normalize('آدم'));
        $this->assertSame('الله', ArabicNormalizer::normalize('ٱلله'));

        $combined = 'أحمد وإسماعيل في آسيا بٱسم الله';
        $this->assertSame('احمد واسماعيل في اسيا باسم الله', ArabicNormalizer::normalize($combined));
    }

    /**
     * Test Rule 4: Normalize Hamza variants: [ؤئ] -> ء.
     */
    public function test_rule_4_normalizes_hamza_variants(): void
    {
        $this->assertSame('مسءول', ArabicNormalizer::normalize('مسؤول'));
        $this->assertSame('مسءول', ArabicNormalizer::normalize('مسئول'));
        $this->assertSame('شاطء', ArabicNormalizer::normalize('شاطئ'));
        $this->assertSame('مءتمر', ArabicNormalizer::normalize('مؤتمر'));
        $this->assertSame('رءيس', ArabicNormalizer::normalize('رئيس'));

        // Both spellings of مسؤول and مسئول normalize to identical string
        $this->assertSame(
            ArabicNormalizer::normalize('مسؤول'),
            ArabicNormalizer::normalize('مسئول')
        );

        $this->assertTrue(ArabicNormalizer::matches('مسؤول', 'مسئول'));
    }

    /**
     * Test Rule 5: Normalize Ta Marbuta: ة -> ه.
     */
    public function test_rule_5_normalizes_ta_marbuta(): void
    {
        $this->assertSame('مجموعه', ArabicNormalizer::normalize('مجموعة'));
        $this->assertSame('القاهره', ArabicNormalizer::normalize('القاهرة'));
        $this->assertSame('مدرسه', ArabicNormalizer::normalize('مدرسة'));
        $this->assertSame('شركه', ArabicNormalizer::normalize('شركة'));

        $this->assertTrue(ArabicNormalizer::matches('مجموعة', 'مجموعه'));
        $this->assertTrue(ArabicNormalizer::matches('مجموعه', 'مجموعة'));
    }

    /**
     * Test Rule 6: Normalize Alif Maqsura: ى -> ي.
     */
    public function test_rule_6_normalizes_alif_maqsura(): void
    {
        $this->assertSame('مستشفي', ArabicNormalizer::normalize('مستشفى'));
        $this->assertSame('علي', ArabicNormalizer::normalize('على'));
        $this->assertSame('فتوي', ArabicNormalizer::normalize('فتوى'));
        $this->assertSame('سلمي', ArabicNormalizer::normalize('سلمى'));

        $this->assertTrue(ArabicNormalizer::matches('مستشفى', 'مستشفي'));
        $this->assertTrue(ArabicNormalizer::matches('مستشفي', 'مستشفى'));
    }

    /**
     * Test Rule 7: Normalize mixed English queries (lowercase, trim punctuation).
     */
    public function test_rule_7_normalizes_mixed_english_and_punctuation(): void
    {
        $input = '  iPhone 15 Pro, Max!  ';
        $this->assertSame('iphone 15 pro max', ArabicNormalizer::normalize($input));

        $bilingual = '«مَجْمُوعَةُ المَعَادِي» - Branch #12 (Cairo, Egypt)!';
        $this->assertSame(
            'مجموعه المعادي branch 12 cairo egypt',
            ArabicNormalizer::normalize($bilingual)
        );

        // Arabic punctuation (comma ،, semicolon ؛, question mark ؟)
        $arabicPunctuation = 'مجموعة، المعادي؛ القاهرة؟';
        $this->assertSame('مجموعه المعادي القاهره', ArabicNormalizer::normalize($arabicPunctuation));
    }

    /**
     * Test null and empty input handling.
     */
    public function test_null_and_empty_inputs(): void
    {
        $this->assertSame('', ArabicNormalizer::normalize(null));
        $this->assertSame('', ArabicNormalizer::normalize(''));
        $this->assertSame('', ArabicNormalizer::normalize('    '));
        $this->assertSame('', ArabicNormalizer::normalize('--- ??? !!!'));

        $this->assertSame([], ArabicNormalizer::tokenize(null));
        $this->assertSame([], ArabicNormalizer::tokenize(''));
        $this->assertSame([], ArabicNormalizer::tokenize('   '));

        $this->assertFalse(ArabicNormalizer::matches(null, 'المعادي'));
        $this->assertFalse(ArabicNormalizer::matches('معادي', null));
        $this->assertFalse(ArabicNormalizer::matches('', 'المعادي'));
    }

    /**
     * Test tokenize method returning normalized words.
     */
    public function test_tokenize_returns_normalized_words(): void
    {
        $tokens = ArabicNormalizer::tokenize('مَجْمُوعَة المَعَادِي');
        $this->assertSame(['مجموعه', 'المعادي'], $tokens);

        $mixedTokens = ArabicNormalizer::tokenize('Dr. Ahmed في المعادي!');
        $this->assertSame(['dr', 'ahmed', 'في', 'المعادي'], $mixedTokens);
    }

    /**
     * Test tokenize with article expansion.
     */
    public function test_tokenize_with_article_expansion(): void
    {
        $tokens = ArabicNormalizer::tokenize('مَجْمُوعَة المَعَادِي', true);

        $this->assertContains('مجموعه', $tokens);
        $this->assertContains('المعادي', $tokens);
        $this->assertContains('معادي', $tokens);
    }

    /**
     * Test stripDefiniteArticle helper.
     */
    public function test_strip_definite_article(): void
    {
        $this->assertSame('معادي', ArabicNormalizer::stripDefiniteArticle('المعادي'));
        $this->assertSame('قاهره', ArabicNormalizer::stripDefiniteArticle('القاهرة'));
        $this->assertSame('كبير', ArabicNormalizer::stripDefiniteArticle('الكبير'));

        // Short words (< 4 chars) or protected word 'الله' should not be stripped
        $this->assertSame('الله', ArabicNormalizer::stripDefiniteArticle('الله'));
        $this->assertSame('الم', ArabicNormalizer::stripDefiniteArticle('ألم'));
    }

    /**
     * Test search query matching across varied spelling patterns.
     */
    public function test_matches_across_varied_arabic_spelling_patterns(): void
    {
        // Tashkeel variants
        $this->assertTrue(ArabicNormalizer::matches('مجموعة', 'مَجْمُوعَة'));
        $this->assertTrue(ArabicNormalizer::matches('مَجْمُوعَة', 'مجموعة'));

        // Tatweel variants
        $this->assertTrue(ArabicNormalizer::matches('معادي', 'مــــعــــادي'));

        // Alef variants
        $this->assertTrue(ArabicNormalizer::matches('احمد', 'أحمد'));
        $this->assertTrue(ArabicNormalizer::matches('ابراهيم', 'إبراهيم'));

        // Hamza variants
        $this->assertTrue(ArabicNormalizer::matches('مسؤول', 'مسئول'));

        // Ta Marbuta variants
        $this->assertTrue(ArabicNormalizer::matches('شركة', 'شركه'));

        // Alif Maqsura variants
        $this->assertTrue(ArabicNormalizer::matches('مستشفى', 'مستشفي'));

        // Mixed case English
        $this->assertTrue(ArabicNormalizer::matches('cairo', 'Cairo'));
        $this->assertTrue(ArabicNormalizer::matches('IPHONE', 'iphone 15'));

        // Multi-word search
        $this->assertTrue(ArabicNormalizer::matches('مجموعة معادي', 'مَجْمُوعَة المَعَادِي'));

        // Negative matches
        $this->assertFalse(ArabicNormalizer::matches('الاسكندرية', 'مَجْمُوعَة المَعَادِي'));
        $this->assertFalse(ArabicNormalizer::matches('غير موجود', 'مَجْمُوعَة المَعَادِي'));
    }
}
