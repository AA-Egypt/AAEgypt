@php
    $locale = app()->getLocale();
    $group = $meeting->group ?? null;
    $groupNameEn = $group?->name_en ?? '';
    $groupNameAr = $group?->name_ar ?? '';
    $groupName = ($locale === 'ar' ? ($groupNameAr ?: $groupNameEn) : ($groupNameEn ?: $groupNameAr)) ?: ($meeting->name ?? $meeting->title ?? __('Group'));
    $meetingTitle = $meeting->name ?? $meeting->title ?? '';

    $daysEn = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $daysAr = [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];

    $dayVal = $meeting->day_of_week;
    $dayName = $dayVal;
    if (is_numeric($dayVal)) {
        $intDay = (int) $dayVal;
        $dayName = $locale === 'ar' ? ($daysAr[$intDay] ?? $dayVal) : ($daysEn[$intDay] ?? $dayVal);
    }

    $isToday = (bool) ($meeting->is_today ?? false);

    $startTime = $meeting->start_time_formatted ?? $meeting->start_time ?? $meeting->time ?? '';
    $startTimeRaw = $meeting->start_time ?: $meeting->time ?: '';
    $endTime = $meeting->end_time_formatted ?? $meeting->end_time ?? '';
    $endTimeRaw = $meeting->end_time ?: '';

    $type = $meeting->type ?? 'in_person';
    $typeLabel = match($type) {
        'online' => $locale === 'ar' ? 'عبر الإنترنت (Online)' : 'Online',
        'hybrid' => $locale === 'ar' ? 'مختلط (Hybrid)' : 'Hybrid',
        default => $locale === 'ar' ? 'حضوري (In-Person)' : 'In-Person',
    };

    $venueAddress = $meeting->venue_address ?? $meeting->venue ?? '';
    $city = $meeting->city ?? '';

    $lat = $meeting->latitude ? (float) $meeting->latitude : null;
    $lng = $meeting->longitude ? (float) $meeting->longitude : null;
@endphp

<article
    class="meeting-card bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col justify-between transition hover:shadow-md hover:border-slate-300 relative text-start"
    data-meeting-card
    data-id="{{ $meeting->id }}"
    data-lat="{{ $lat ?? '' }}"
    data-lng="{{ $lng ?? '' }}"
    data-city="{{ $city }}"
    data-is-today="{{ $isToday ? 'true' : 'false' }}"
    data-day="{{ $meeting->day_of_week }}"
    data-type="{{ $type }}"
>
    <div class="space-y-3">
        <!-- Top row: Badges (Distance, Type, Today) -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Meeting Type Badge -->
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $type === 'online' ? 'bg-purple-100 text-purple-800' : ($type === 'hybrid' ? 'bg-teal-100 text-teal-800' : 'bg-blue-100 text-blue-800') }}">
                    {{ $typeLabel }}
                </span>

                <!-- Today Indicator Badge -->
                @if($isToday)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        {{ $locale === 'ar' ? 'اليوم (Today)' : 'Today' }}
                    </span>
                @endif
            </div>

            <!-- Client-side Distance Badge (Populated dynamically by nearby-locator.js) -->
            <span
                class="distance-badge hidden inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300"
                data-distance-badge
            ></span>
        </div>

        <!-- Group Name & Meeting Title -->
        <div>
            <h3 class="text-lg font-bold text-slate-900 tracking-tight leading-snug">
                {{ $groupName }}
            </h3>
            @if($meetingTitle && $meetingTitle !== $groupName)
                <p class="text-sm font-medium text-slate-600 mt-0.5">
                    {{ $meetingTitle }}
                </p>
            @endif
        </div>

        <!-- Schedule: Day and Time -->
        <div class="flex items-center gap-2 text-sm text-slate-700">
            <span class="font-medium text-slate-900">📅 {{ $dayName }}</span>
            <span class="text-slate-400">•</span>
            <span class="font-medium text-slate-900">
                🕒 {{ $startTime }}@if($startTimeRaw && $startTimeRaw !== $startTime) <span class="text-xs text-slate-500 font-normal">({{ $startTimeRaw }})</span>@endif @if($endTime) - {{ $endTime }}@if($endTimeRaw && $endTimeRaw !== $endTime) <span class="text-xs text-slate-500 font-normal">({{ $endTimeRaw }})</span>@endif @endif
            </span>
        </div>

        <!-- Venue Address & City -->
        @if($venueAddress || $city)
            <div class="text-sm text-slate-600 flex items-start gap-1.5">
                <span class="text-slate-400 shrink-0 mt-0.5">📍</span>
                <span class="leading-relaxed">
                    {{ $venueAddress }}@if($venueAddress && $city), @endif{{ $city }}
                </span>
            </div>
        @endif

        <!-- Online link if available -->
        @if(!empty($meeting->online_link))
            <div class="text-xs text-slate-600 pt-1">
                <a href="{{ $meeting->online_link }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline inline-flex items-center gap-1 font-medium">
                    <span>🔗</span>
                    <span>{{ $locale === 'ar' ? 'رابط الانضمام عبر الإنترنت' : 'Online Meeting Link' }}</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Direction Links / Action Buttons -->
    @if(!empty($lat) && !empty($lng))
        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <a
                href="https://www.google.com/maps/dir/?api=1&destination={{ $lat }},{{ $lng }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 transition"
                title="Open in Google Maps"
                aria-label="Open in Google Maps"
            >
                <span>🗺️</span>
                <span>Open in Google Maps</span>
            </a>

            <a
                href="https://maps.apple.com/?daddr={{ $lat }},{{ $lng }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-200 transition"
                title="Open in Apple Maps"
                aria-label="Open in Apple Maps"
            >
                <span>🍎</span>
                <span>Open in Apple Maps</span>
            </a>
        </div>
    @endif
</article>
