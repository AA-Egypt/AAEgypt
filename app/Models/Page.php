<?php

namespace App\Models;

use App\Services\Search\ArabicNormalizer;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $fillable = [
        'title_ar',
        'title_en',
        'slug',
        'body_ar',
        'body_en',
        'is_published',
        'meta_description_ar',
        'meta_description_en',
        'search_vector',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if (empty($page->slug)) {
                $source = ! empty($page->title_en) ? $page->title_en : $page->title_ar;
                $page->slug = Str::slug($source);
            }

            $text = implode(' ', array_filter([
                $page->title_ar,
                $page->title_en,
                strip_tags((string) $page->body_ar),
                strip_tags((string) $page->body_en),
            ]));

            $page->search_vector = ArabicNormalizer::normalize($text);
        });
    }
}
