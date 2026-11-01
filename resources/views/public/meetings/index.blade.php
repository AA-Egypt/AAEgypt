@extends('layouts.public')

@php
    $locale = app()->getLocale();

    $uniqueCities = collect($meetings)
        ->pluck('city')
        ->filter()
        ->unique(fn($c) => mb_strtolower($c))
        ->sort()
        ->values();

    $meetingsJsonData = collect($meetings)->map(function ($meeting) {
        $lat = $meeting->latitude ? (float) $meeting->latitude : null;
        $lng = $meeting->longitude ? (float) $meeting->longitude : null;
        return [
            'id' => $meeting->id,
            'name' => $meeting->name ?? $meeting->title,
            'city' => $meeting->city ?? '',
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'day_of_week' => $meeting->day_of_week,
            'is_today' => (bool) ($meeting->is_today ?? false),
            'type' => $meeting->type ?? 'in_person',
        ];
    })->values()->all();
@endphp

@section('content')
<div class="max-w-6xl mx-auto py-8 text-start space-y-8">
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">
            {{ __('Meetings') }}
        </h1>
        <p class="text-slate-600 leading-relaxed max-w-3xl">
            @if($locale === 'ar')
                دليل واجتماعات زمالة مدمني الخمر المجهولين في جمهورية مصر العربية. جميع الاجتماعات مجانية وسرية ومفتوحة للراغبين في التعافي.
            @else
                Directory and meetings of Alcoholics Anonymous in Egypt. All meetings are free, confidential, and open to anyone seeking recovery.
            @endif
        </p>
    </div>

    <!-- Client-side "Nearby and Nearest" Meeting Finder (Privacy by Design) -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs space-y-4" data-nearby-locator-container>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    {{ $locale === 'ar' ? 'البحث عن الاجتماعات القريبة' : 'Find Nearby Meetings' }}
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $locale === 'ar' ? 'خصوصيتك محمية: لا يتم إرسال إحداثياتك إلى الخادم مطلقاً، ويتم حساب المسافة بالكامل داخل متصفحك.' : 'Privacy by Design: Your GPS coordinates are never sent to our servers. Distance calculations happen 100% inside your browser.' }}
                </p>
            </div>

            <!-- Action Button & Fallback Controls -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Explicit Location Trigger -->
                <button
                    type="button"
                    id="find-near-me-btn"
                    data-find-near-me
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-emerald-700 text-white hover:bg-emerald-800 shadow-sm transition active:scale-95 cursor-pointer"
                >
                    <span aria-hidden="true">📍</span>
                    <span>{{ $locale === 'ar' ? 'البحث عن الاجتماعات القريبة مني' : 'Find Meetings Near Me' }}</span>
                </button>

                <!-- Manual Fallback City Dropdown -->
                <div class="flex items-center gap-2">
                    <label for="city-filter" class="sr-only">
                        {{ $locale === 'ar' ? 'اختر المدينة' : 'Select City' }}
                    </label>
                    <select
                        id="city-filter"
                        name="city"
                        data-city-filter
                        class="rounded-xl border border-slate-300 bg-white py-2 px-3 text-sm font-medium text-slate-800 shadow-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                        <option value="">{{ $locale === 'ar' ? 'جميع المدن / المناطق' : 'All Cities / Areas' }}</option>
                        @foreach($uniqueCities as $city)
                            <option value="{{ $city }}" {{ request('city') === $city ? 'selected' : '' }}>
                                {{ $city }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Dynamic Status/Alert Notice (Privacy feedback & Permission Fallback) -->
        <div
            id="locator-status-notice"
            class="hidden text-sm rounded-xl p-3 font-medium transition"
            role="status"
            aria-live="polite"
        ></div>
    </div>

    <!-- JSON Script Tag containing meeting coordinate list for client-side JavaScript -->
    <script id="meetings-data" type="application/json">
        {!! json_encode($meetingsJsonData, JSON_UNESCAPED_UNICODE) !!}
    </script>

    <!-- Meeting Cards Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-slate-900">
                {{ $locale === 'ar' ? 'قائمة الاجتماعات' : 'Meetings List' }}
            </h2>
            <span class="text-xs font-semibold text-slate-500">
                {{ count($meetings) }} {{ $locale === 'ar' ? 'اجتماع' : 'meetings' }}
            </span>
        </div>

        <div
            id="meeting-cards-container"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            data-meetings-container
        >
            @forelse($meetings as $meeting)
                @include('partials.meeting-card', ['meeting' => $meeting])
            @empty
                <div class="col-span-full text-center py-12 text-slate-500 bg-white rounded-xl border border-slate-200">
                    {{ $locale === 'ar' ? 'لا توجد اجتماعات متاحة حالياً.' : 'No meetings currently available.' }}
                </div>
            @endforelse
        </div>
    </div>

    <!-- Meetings Map Section -->
    <div class="space-y-4 pt-6 border-t border-slate-200">
        <h2 class="text-xl font-bold text-slate-900">
            {{ $locale === 'ar' ? 'خريطة الاجتماعات' : 'Meetings Map' }}
        </h2>
        <x-meeting-map :meetings="$meetings ?? []" />
    </div>
</div>
@endsection

