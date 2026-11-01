@extends('layouts.public', [
    'title' => $query !== ''
        ? ($locale === 'ar' ? 'نتائج البحث: ' . $query : 'Search Results: ' . $query)
        : ($locale === 'ar' ? 'البحث في الموقع' : 'Search'),
])

@section('content')
<div class="max-w-5xl mx-auto py-4 sm:py-6">
    <!-- Search Box Header -->
    <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-4 sm:p-6 mb-6">
        <form action="{{ url($locale . '/search') }}" method="GET" role="search">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <label for="main-search-input" class="sr-only">{{ __('Search') }}</label>
                    <input type="search"
                           id="main-search-input"
                           name="q"
                           value="{{ $query }}"
                           autofocus
                           placeholder="{{ $locale === 'ar' ? 'ابحث في الاجتماعات والصفحات والأدبيات...' : 'Search meetings, pages, and literature...' }}"
                           class="w-full ps-11 pe-4 py-3 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50 focus:bg-white text-slate-900 transition"
                    />
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-lg shadow-xs text-base font-medium text-white bg-emerald-700 hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition">
                    {{ __('Search') }}
                </button>
            </div>
        </form>

        @if($query !== '')
            <div class="mt-3 flex flex-wrap items-center justify-between text-xs sm:text-sm text-slate-500">
                <div>
                    @if($locale === 'ar')
                        تم العثور على <strong>{{ $totalCount }}</strong> نتيجة لـ &quot;<span class="text-slate-800 font-medium">{{ $query }}</span>&quot;
                    @else
                        Found <strong>{{ $totalCount }}</strong> results for &quot;<span class="text-slate-800 font-medium">{{ $query }}</span>&quot;
                    @endif
                </div>
                <div class="text-slate-400">
                    @if($locale === 'ar')
                        استغرق البحث {{ $durationMs }} مللي ثانية
                    @else
                        Returned in {{ $durationMs }}ms
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if($query === '')
        <!-- Empty Query Welcome & Popular Topics -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">
                {{ $locale === 'ar' ? 'البحث في اجتماعات وصفحات A.A. مصر' : 'Search A.A. Egypt Meetings & Pages' }}
            </h2>
            <p class="text-slate-600 max-w-lg mx-auto mb-6 text-sm">
                {{ $locale === 'ar'
                    ? 'يمكنك البحث عن مواعيد وأماكن الاجتماعات بالمدينة أو اسم المجموعة، أو تصفح مقالات وصفحات التعافي والأدبيات.'
                    : 'Find meeting times and venues by city or group name, or explore recovery articles, pages, and literature.' }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    {{ $locale === 'ar' ? 'عمليات بحث شائعة:' : 'Popular searches:' }}
                </span>
                @php
                    $commonQueries = $locale === 'ar'
                        ? ['القاهرة', 'خطوات', 'المعادي', 'الإسكندرية', 'التقاليد']
                        : ['Cairo', 'Steps', 'Maadi', 'Alexandria', 'Traditions'];
                @endphp
                @foreach($commonQueries as $common)
                    <a href="{{ url($locale . '/search?q=' . urlencode($common)) }}"
                       class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">
                        {{ $common }}
                    </a>
                @endforeach
            </div>
        </div>
    @elseif($totalCount === 0)
        <!-- No Results Found: Helpful Suggestions -->
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-8">
            <div class="max-w-2xl mx-auto text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 text-amber-700 mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">
                    @if($locale === 'ar')
                        لم نتمكن من العثور على أي نتائج لـ &quot;{{ $query }}&quot;
                    @else
                        No results found for &quot;{{ $query }}&quot;
                    @endif
                </h2>
                <p class="text-slate-600 text-sm mb-6">
                    @if($locale === 'ar')
                        نأسف، لم نجد اجتماعات أو صفحات تطابق مصطلح البحث الذي أدخلته.
                    @else
                        We could not find any meetings or pages matching your search term.
                    @endif
                </p>

                <!-- Helpful suggestions in active language -->
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-5 text-start mb-6">
                    <h3 class="font-semibold text-slate-800 text-sm mb-3">
                        {{ $locale === 'ar' ? 'اقتراحات مفيدة لتحسين نتائج البحث:' : 'Helpful suggestions:' }}
                    </h3>
                    <ul class="list-disc list-inside space-y-1.5 text-sm text-slate-600">
                        @if($locale === 'ar')
                            <li>تأكد من كتابة الكلمات المفتاحية بشكل صحيح دون أخطاء إملائية.</li>
                            <li>جرب استخدام كلمات مفتاحية أكثر عمومية أو تقليل عدد الكلمات.</li>
                            <li>ابحث باسم المدينة باللغة العربية أو الإنجليزية، مثل: <strong>القاهرة</strong> أو <strong>Cairo</strong>.</li>
                            <li>ابحث عن مفاهيم الزمالة، مثل: <strong>خطوات</strong> أو <strong>تقاليد</strong>.</li>
                            <li>الهمزات والتنوين والتاء المربوطة يتم تسويتها تلقائياً عبر نظام البحث العربي.</li>
                        @else
                            <li>Double-check your spelling for any typos.</li>
                            <li>Try using fewer, simpler, or more general keywords.</li>
                            <li>Try searching by Egyptian city names, such as <strong>Cairo</strong> or <strong>Alexandria</strong>.</li>
                            <li>Search for core A.A. topics like <strong>Steps</strong> or <strong>Traditions</strong>.</li>
                            <li>Arabic diacritics and prefixes (like &quot;Al-&quot;) are handled automatically.</li>
                        @endif
                    </ul>
                </div>

                <!-- Quick links to common searches and pages -->
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        {{ $locale === 'ar' ? 'جرب البحث عن:' : 'Try searching for:' }}
                    </span>
                    @php
                        $suggestedQueries = $locale === 'ar'
                            ? ['القاهرة', 'خطوات', 'المعادي', 'الإسكندرية']
                            : ['Cairo', 'Steps', 'Maadi', 'Alexandria'];
                    @endphp
                    @foreach($suggestedQueries as $suggested)
                        <a href="{{ url($locale . '/search?q=' . urlencode($suggested)) }}"
                           class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 hover:bg-emerald-100 transition">
                            {{ $suggested }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <!-- Results Tabs Header -->
        <div class="border-b border-slate-200 mb-6">
            <nav class="flex gap-4 sm:gap-6" aria-label="Search Categories Tabs">
                <!-- Tab: All -->
                <a href="{{ url($locale . '/search?q=' . urlencode($query) . '&tab=all') }}"
                   class="pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $activeTab === 'all' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    <span>{{ $locale === 'ar' ? 'الكل' : 'All' }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $activeTab === 'all' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ $totalCount }}
                    </span>
                </a>

                <!-- Tab: Meetings (الاجتماعات) -->
                <a href="{{ url($locale . '/search?q=' . urlencode($query) . '&tab=meetings') }}"
                   class="pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $activeTab === 'meetings' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    <span>{{ $locale === 'ar' ? 'الاجتماعات' : 'Meetings' }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $activeTab === 'meetings' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ $meetings->count() }}
                    </span>
                </a>

                <!-- Tab: Articles & Pages (الصفحات) -->
                <a href="{{ url($locale . '/search?q=' . urlencode($query) . '&tab=pages') }}"
                   class="pb-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 {{ $activeTab === 'pages' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
                    <span>{{ $locale === 'ar' ? 'الصفحات' : 'Articles & Pages' }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $activeTab === 'pages' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ $pages->count() }}
                    </span>
                </a>
            </nav>
        </div>

        <div class="space-y-8">
            <!-- Section: Meetings (الاجتماعات) -->
            @if(($activeTab === 'all' || $activeTab === 'meetings') && $meetings->isNotEmpty())
                <section aria-labelledby="section-meetings-title">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="section-meetings-title" class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <span>👥</span>
                            <span>{{ $locale === 'ar' ? 'الاجتماعات' : 'Meetings' }}</span>
                            <span class="text-xs font-normal text-slate-500">({{ $meetings->count() }})</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($meetings as $meeting)
                            <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs hover:border-emerald-300 transition flex flex-col justify-between">
                                <div>
                                    <!-- Badges: Category & Day/Time -->
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <!-- Category Badge -->
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                            {{ $locale === 'ar' ? 'الاجتماعات' : 'Meetings' }}
                                        </span>

                                        <!-- Day/Time Badge -->
                                        @if($meeting->day_of_week || $meeting->time)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                🕒 {{ trim(($meeting->day_of_week ?? '') . ' ' . ($meeting->time ?? '')) }}
                                            </span>
                                        @endif

                                        <!-- City Badge -->
                                        @if($meeting->city)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                                📍 {{ $meeting->city }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Meeting Title -->
                                    <h3 class="text-base font-semibold text-slate-900 mb-2">
                                        {!! $meeting->highlighted_title !!}
                                    </h3>

                                    <!-- Excerpt with highlighted keywords -->
                                    @if($meeting->highlighted_excerpt)
                                        <p class="text-sm text-slate-600 line-clamp-3">
                                            {!! $meeting->highlighted_excerpt !!}
                                        </p>
                                    @endif
                                </div>

                                @if($meeting->venue)
                                    <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500 flex items-center gap-1">
                                        <span class="font-medium text-slate-700">{{ $locale === 'ar' ? 'المكان:' : 'Venue:' }}</span>
                                        <span>{{ $meeting->venue }}</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Section: Articles & Pages (الصفحات) -->
            @if(($activeTab === 'all' || $activeTab === 'pages') && $pages->isNotEmpty())
                <section aria-labelledby="section-pages-title">
                    <div class="flex items-center justify-between mb-4">
                        <h2 id="section-pages-title" class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <span>📄</span>
                            <span>{{ $locale === 'ar' ? 'الصفحات' : 'Articles & Pages' }}</span>
                            <span class="text-xs font-normal text-slate-500">({{ $pages->count() }})</span>
                        </h2>
                    </div>

                    <div class="space-y-4">
                        @foreach($pages as $page)
                            <div class="bg-white rounded-lg border border-slate-200 p-5 shadow-xs hover:border-purple-300 transition">
                                <!-- Category Badge -->
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        {{ $locale === 'ar' ? 'الصفحات' : 'Articles & Pages' }}
                                    </span>
                                </div>

                                <!-- Page Title Linked -->
                                <h3 class="text-base font-semibold mb-2">
                                    <a href="{{ url($locale . '/' . $page->slug) }}"
                                       class="text-emerald-800 hover:text-emerald-900 hover:underline">
                                        {!! $page->highlighted_title !!}
                                    </a>
                                </h3>

                                <!-- Highlighted Excerpt -->
                                @if($page->highlighted_excerpt)
                                    <p class="text-sm text-slate-600 leading-relaxed">
                                        {!! $page->highlighted_excerpt !!}
                                    </p>
                                @endif

                                <div class="mt-3">
                                    <a href="{{ url($locale . '/' . $page->slug) }}"
                                       class="text-xs font-medium text-emerald-700 hover:text-emerald-800 inline-flex items-center gap-1">
                                        <span>{{ $locale === 'ar' ? 'قراءة المزيد' : 'Read more' }}</span>
                                        <span aria-hidden="true">{{ $locale === 'ar' ? '←' : '→' }}</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Tab Empty Notifications -->
            @if($activeTab === 'meetings' && $meetings->isEmpty())
                <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-slate-500 text-sm">
                    {{ $locale === 'ar' ? 'لا توجد اجتماعات تطابق بحثك.' : 'No meetings match your search query.' }}
                </div>
            @endif

            @if($activeTab === 'pages' && $pages->isEmpty())
                <div class="bg-white rounded-lg border border-slate-200 p-8 text-center text-slate-500 text-sm">
                    {{ $locale === 'ar' ? 'لا توجد صفحات أو أدبيات تطابق بحثك.' : 'No pages or articles match your search query.' }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection

