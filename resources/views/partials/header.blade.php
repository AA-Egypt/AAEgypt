@php
    $currentLocale = app()->getLocale();
    $targetLocale = $currentLocale === 'ar' ? 'en' : 'ar';
    $targetLabel = $currentLocale === 'ar' ? 'English' : 'العربية';

    $segments = request()->segments();
    if (!empty($segments) && in_array($segments[0], ['ar', 'en'], true)) {
        $segments[0] = $targetLocale;
        $switchUrl = url(implode('/', $segments));
    } elseif (empty($segments)) {
        $switchUrl = url($targetLocale);
    } else {
        $switchUrl = url($targetLocale . '/' . implode('/', $segments));
    }
    if ($queryString = request()->getQueryString()) {
        $switchUrl .= '?' . $queryString;
    }

    $navItems = [
        ['label' => __('Home'), 'url' => url($currentLocale)],
        ['label' => __('Who We Are'), 'url' => url($currentLocale . '/who-we-are')],
        ['label' => __('Meetings'), 'url' => url($currentLocale . '/meetings')],
        ['label' => __('Privacy'), 'url' => url($currentLocale . '/privacy')],
    ];
@endphp

<header class="bg-white border-b border-slate-200 shadow-xs">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand / Logo -->
            <div class="flex items-center">
                <a href="{{ url($currentLocale) }}" class="flex items-center gap-2 text-emerald-800 font-bold text-xl tracking-tight text-start">
                    <img src="{{ asset('images/emblem.png') }}" alt="{{ config('app.name', 'A.A. Egypt') }}" class="h-9 w-9 object-contain" />
                    <span>{{ config('app.name', 'A.A. Egypt') }}</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center gap-6" aria-label="Main Navigation">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" class="text-sm font-medium text-slate-600 hover:text-emerald-700 transition text-start">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <!-- Navigation Search Form -->
            <div class="hidden lg:flex items-center">
                <form action="{{ url($currentLocale . '/search') }}" method="GET" class="relative" role="search">
                    <label for="header-search" class="sr-only">{{ __('Search') }}</label>
                    <input type="search"
                           id="header-search"
                           name="q"
                           value="{{ request('q') }}"
                           placeholder="{{ $currentLocale === 'ar' ? 'بحث في الاجتماعات والصفحات...' : 'Search meetings & pages...' }}"
                           class="w-48 xl:w-64 ps-9 pe-3 py-1.5 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-2xs"
                    />
                    <div class="absolute inset-y-0 start-0 flex items-center ps-2.5 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </form>
            </div>

            <!-- Desktop Language Switcher & Actions -->
            <div class="hidden md:flex items-center gap-4">
                <a href="{{ $switchUrl }}"
                   id="language-switcher"
                   data-testid="language-switcher"
                   class="language-switcher inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:text-emerald-700 transition shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                   aria-label="{{ __('Switch Language') }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                    </svg>
                    <span>{{ $targetLabel }}</span>
                </a>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-emerald-700">
                            {{ __('Log in') }}
                        </a>
                    @endauth
                @endif
            </div>

            <!-- Mobile Controls (Hamburger & Language Toggle) -->
            <div class="flex md:hidden items-center gap-2">
                <a href="{{ $switchUrl }}"
                   class="language-switcher inline-flex items-center px-2.5 py-1 text-xs font-medium rounded border border-slate-300 text-slate-700 bg-white hover:bg-slate-50"
                   aria-label="{{ __('Switch Language') }}">
                    {{ $targetLabel }}
                </a>
                <button type="button"
                        id="mobile-menu-toggle"
                        class="inline-flex items-center justify-center p-2 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500"
                        aria-controls="mobile-menu"
                        aria-expanded="false"
                        onclick="const menu = document.getElementById('mobile-menu'); const isExpanded = this.getAttribute('aria-expanded') === 'true'; this.setAttribute('aria-expanded', !isExpanded); menu.classList.toggle('hidden');">
                    <span class="sr-only">{{ __('Toggle navigation') }}</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile dropdown menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 py-3">
            <form action="{{ url($currentLocale . '/search') }}" method="GET" class="px-3 pb-3" role="search">
                <div class="relative">
                    <label for="mobile-header-search" class="sr-only">{{ __('Search') }}</label>
                    <input type="search"
                           id="mobile-header-search"
                           name="q"
                           value="{{ request('q') }}"
                           placeholder="{{ $currentLocale === 'ar' ? 'بحث في الاجتماعات والصفحات...' : 'Search meetings & pages...' }}"
                           class="w-full ps-9 pe-3 py-2 text-sm rounded-md border border-slate-300 bg-slate-50 focus:bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    />
                    <div class="absolute inset-y-0 start-0 flex items-center ps-2.5 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </form>
            <nav class="flex flex-col gap-2">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}"
                       class="px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900 text-start">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                @if (Route::has('login'))
                    <div class="pt-2 border-t border-slate-100">
                        @auth
                            <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-md text-base font-medium text-emerald-700 hover:bg-slate-100 text-start">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 text-start">
                                {{ __('Log in') }}
                            </a>
                        @endauth
                    </div>
                @endif
            </nav>
        </div>
    </div>
</header>

