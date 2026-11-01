<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Display the specified bilingual page.
     */
    public function show(string $locale, string $slug): View
    {
        $page = Page::where('slug', $slug)->first();

        if ($page) {
            if (! $page->is_published) {
                abort(404);
            }

            return view('public.page', [
                'page' => $page,
                'locale' => $locale,
                'title' => $locale === 'ar' ? $page->title_ar : $page->title_en,
            ]);
        }

        if (view()->exists('public.'.$slug)) {
            return view('public.'.$slug, [
                'locale' => $locale,
            ]);
        }

        abort(404);
    }
}
