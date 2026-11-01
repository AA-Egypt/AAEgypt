@props([
    'meetings' => [],
    'markers' => null,
    'center' => [30.0444, 31.2357],
    'zoom' => 11,
    'id' => null,
])

@php
    $elementId = $id ?? ('meeting-map-' . str_replace('.', '', uniqid('', true)));
    $locale = app()->getLocale();

    if ($markers === null) {
        $markers = collect($meetings)->filter(function ($meeting) {
            return !empty($meeting->latitude) && !empty($meeting->longitude);
        })->map(function ($meeting) use ($locale) {
            $group = $meeting->group ?? null;
            $groupNameEn = $group?->name_en ?? '';
            $groupNameAr = $group?->name_ar ?? '';
            $meetingName = $meeting->name ?? $meeting->title ?? ($locale === 'ar' ? ($groupNameAr ?: $groupNameEn) : ($groupNameEn ?: $groupNameAr));
            $address = $meeting->venue_address ?? $meeting->venue ?? '';
            $city = $meeting->city ?? '';
            $time = $meeting->start_time_formatted ?? $meeting->start_time ?? $meeting->time ?? '';
            $dayOfWeek = $meeting->day_of_week ?? '';
            $lat = (float) $meeting->latitude;
            $lng = (float) $meeting->longitude;

            return [
                'id' => $meeting->id ?? null,
                'lat' => $lat,
                'lng' => $lng,
                'name' => $meetingName,
                'group_name_en' => $groupNameEn,
                'group_name_ar' => $groupNameAr,
                'address' => $address,
                'city' => $city,
                'time' => $time,
                'day' => $dayOfWeek,
                'direction_url' => "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}",
            ];
        })->values()->all();
    }
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full h-[500px] rounded-xl overflow-hidden border border-slate-200 shadow-sm bg-slate-100']) }}>
    <div
        id="{{ $elementId }}"
        class="meeting-map-container w-full h-full"
        data-meeting-map
        data-center="{{ json_encode($center) }}"
        data-zoom="{{ $zoom }}"
        data-locale="{{ $locale }}"
        data-markers="{{ json_encode($markers, JSON_UNESCAPED_UNICODE) }}"
    ></div>
</div>
