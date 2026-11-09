@extends('layouts.public', ['title' => __('Dashboard')])

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <!-- Profile & Welcome Banner -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xl shrink-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h1>
                        @if($user->role === 'web_admin')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                {{ __('Web Administrator') }}
                            </span>
                        @elseif($user->role === 'gsr')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                {{ __('GSR') }}
                            </span>
                        @elseif($user->role === 'delegate')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                {{ __('Delegate') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                        <span>{{ $user->email }}</span>
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            {{ __('MFA Active') }}
                        </span>
                    </p>

                    @if($group)
                        <p class="text-sm text-slate-600 mt-2 font-medium">
                            <span class="text-slate-400">{{ __('Assigned Group:') }}</span>
                            {{ app()->getLocale() === 'ar' ? ($group->name_ar ?: $group->name_en) : ($group->name_en ?: $group->name_ar) }}
                        </p>
                    @endif

                    @if($activeServiceTerm)
                        <p class="text-xs text-slate-500 mt-1">
                            {{ __('Service Term:') }} {{ $activeServiceTerm->start_date?->format('Y-m-d') }} &ndash; {{ $activeServiceTerm->end_date?->format('Y-m-d') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 hover:text-red-600 transition shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Service Tools Grid -->
    <div>
        <h2 class="text-lg font-bold text-slate-900 mb-4">{{ __('Service Tools & Portals') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            @if($user->role === 'web_admin')
            <!-- Admin Panel Card -->
            <div class="bg-gradient-to-br from-amber-500/10 via-white to-white rounded-2xl border-2 border-amber-300 p-6 flex flex-col justify-between shadow-xs hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                            {{ __('Admin Only') }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mt-4">{{ __('Filament Admin Panel') }}</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ __('Manage bilingual CMS pages, site banners & announcements, overview stats, and system settings.') }}
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="/admin" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition shadow-xs">
                        {{ __('Open Admin Panel') }} &rarr;
                    </a>
                </div>
            </div>
            @endif

            <!-- Meeting Editor Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 flex flex-col justify-between shadow-xs hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $meetingsCount }} {{ __('Meetings') }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mt-4">{{ __('Meeting Directory Editor') }}</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ __('Livewire meeting management: update meeting days, hours, venue addresses, and interactive map coordinates.') }}
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('service.meetings') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-xs">
                        {{ __('Open Meeting Editor') }} &rarr;
                    </a>
                </div>
            </div>

            <!-- App Passwords Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 flex flex-col justify-between shadow-xs hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                            {{ $appPasswordsCount }} {{ __('Active') }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mt-4">{{ __('App Passwords') }}</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ __('Generate and manage application passwords for email clients (Outlook, Apple Mail, Thunderbird) connecting via IMAPS / SMTP.') }}
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('service.app-passwords.index') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        {{ __('Manage App Passwords') }} &rarr;
                    </a>
                </div>
            </div>

            <!-- Public Meeting Locator Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-6 flex flex-col justify-between shadow-xs hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 rounded-xl bg-slate-700 text-white flex items-center justify-center shadow-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mt-4">{{ __('Public Meeting Finder') }}</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ __('Browse the live public meeting directory, filtering by city, language, and day of week.') }}
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ url(app()->getLocale() . '/meetings') }}" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        {{ __('View Live Directory') }} &rarr;
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
