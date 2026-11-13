<div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                    {{ __('Self-Service Meeting Editor') }}
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    {{ __('Updates publish immediately to the live public directory.') }}
                </p>
            </div>
            @if($group)
                <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 mr-2 rtl:ml-2"></span>
                    {{ $group->name_en ?? $group->name_ar ?? 'Assigned Group' }}
                </div>
            @endif
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session()->has('message') || session()->has('success') || session()->has('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-xs" role="alert">
            <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="text-sm font-semibold">
                    {{ session('message') ?? session('success') ?? session('status') }}
                </p>
                <p class="text-xs text-emerald-600 mt-0.5">
                    {{ __('Your changes are now live and visible to all visitors.') }}
                </p>
            </div>
        </div>
    @endif

    <!-- Multiple Meetings Selector -->
    @if ($meetings->isNotEmpty())
        <div class="mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <label for="meeting-selector" class="block text-sm font-medium text-slate-700 mb-2">
                {{ __('Your Group\'s Meetings') }}
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                @foreach ($meetings as $item)
                    <button
                        type="button"
                        wire:click="selectMeeting({{ $item->id }})"
                        class="p-3 text-start rounded-lg border text-sm transition-colors {{ (int)$selectedMeetingId === (int)$item->id ? 'border-indigo-600 bg-indigo-50/50 text-indigo-900 font-semibold' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700' }}"
                    >
                        <div class="truncate font-medium">{{ $item->name ?? $item->title ?? 'Meeting #'.$item->id }}</div>
                        <div class="text-xs text-slate-500 mt-1">
                            @php
                                $dayNames = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
                                $dayStr = $dayNames[$item->day_of_week] ?? '';
                            @endphp
                            {{ $dayStr }} &bull; {{ $item->start_time ?? $item->time }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($meetings->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center text-amber-800">
            <p class="font-medium">{{ __('No meetings found for your assigned group.') }}</p>
            <p class="text-xs text-amber-600 mt-1">{{ __('Please contact your Web Administrator if this is unexpected.') }}</p>
        </div>
    @else
        <!-- Meeting Edit Form -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">
                    {{ __('Editing:') }} <span class="text-indigo-600">{{ $name ?: 'Meeting' }}</span>
                </h2>
                <span class="text-xs text-slate-500 font-mono">ID: #{{ $meetingId }}</span>
            </div>
            <form wire:submit="save" class="p-6 sm:p-8 space-y-6">
                <!-- Hidden version for optimistic locking -->
                <input type="hidden" wire:model="form_version" name="form_version" value="{{ $form_version }}" />

                <!-- Edit Conflict Notification Banner -->
                @if ($hasConflict || $errors->has('form_version') || session()->has('conflict_error'))
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-xs" role="alert">
                        <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-semibold">
                                {{ $conflictMessage ?? session('conflict_error') ?? $errors->first('form_version') }}
                            </p>
                            <div class="mt-2 flex items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="$set('showConflictModal', true)"
                                    class="text-xs font-bold text-rose-700 underline cursor-pointer"
                                >
                                    {{ __('View Conflicting Changes') }}
                                </button>
                                <span class="text-slate-300">&bull;</span>
                                <button
                                    type="button"
                                    wire:click="resolveConflictReload"
                                    class="text-xs font-semibold text-slate-700 hover:text-slate-900 cursor-pointer"
                                >
                                    {{ __('Discard My Edits & Reload') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- Row 1: Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Meeting Name / Title') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model="name"
                        value="{{ $name }}"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                        placeholder="e.g. Zamalek Serenity Group"
                        required
                    />
                    @error('name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Row 2: Day, Start Time, End Time -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="day_of_week" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Day of Week') }} <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="day_of_week"
                            wire:model="day_of_week"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            required
                        >
                            <option value="">{{ __('-- Select Day --') }}</option>
                            <option value="0">{{ __('Sunday / الأحد') }}</option>
                            <option value="1">{{ __('Monday / الإثنين') }}</option>
                            <option value="2">{{ __('Tuesday / الثلاثاء') }}</option>
                            <option value="3">{{ __('Wednesday / الأربعاء') }}</option>
                            <option value="4">{{ __('Thursday / الخميس') }}</option>
                            <option value="5">{{ __('Friday / الجمعة') }}</option>
                            <option value="6">{{ __('Saturday / السبت') }}</option>
                        </select>
                        @error('day_of_week') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="start_time" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Start Time') }} (H:i) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="time"
                            id="start_time"
                            wire:model="start_time"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            required
                        />
                        @error('start_time') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="end_time" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('End Time') }} (H:i) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="time"
                            id="end_time"
                            wire:model="end_time"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            required
                        />
                        @error('end_time') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Row 3: Address & City -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Venue Address') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="address"
                            wire:model="address"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            placeholder="e.g. 15 Brazil Street, All Saints Cathedral"
                            required
                        />
                        @error('address') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('City') }}
                        </label>
                        <input
                            type="text"
                            id="city"
                            wire:model="city"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            placeholder="e.g. Cairo"
                        />
                        @error('city') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Coordinates & Interactive Leaflet Map Section -->
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <span class="block text-sm font-semibold text-slate-800">
                                {{ __('Location Pin & Coordinates') }}
                            </span>
                            <span class="text-xs text-slate-500">
                                {{ __('Drag the map marker or click on the map to confirm meeting coordinates.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Map Container with wire:ignore -->
                    <div
                        wire:ignore
                        id="meeting-editor-map"
                        data-lat="{{ $latitude ?? 30.0444 }}"
                        data-lng="{{ $longitude ?? 31.2357 }}"
                        class="w-full h-64 sm:h-72 rounded-xl border border-slate-300 bg-slate-100 mb-3 z-0 relative"
                    ></div>

                    <!-- Coordinate inputs and Pin Confirmation -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label for="latitude-input" class="block text-xs font-medium text-slate-600 mb-1">
                                {{ __('Latitude') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                id="latitude-input"
                                name="latitude"
                                wire:model="latitude"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 text-slate-900 text-xs shadow-xs focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            />
                            @error('latitude') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="longitude-input" class="block text-xs font-medium text-slate-600 mb-1">
                                {{ __('Longitude') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="any"
                                id="longitude-input"
                                name="longitude"
                                wire:model="longitude"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 text-slate-900 text-xs shadow-xs focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            />
                            @error('longitude') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="coordinates-input" class="block text-xs font-medium text-slate-600 mb-1">
                                {{ __('Coordinates (Lat, Lng)') }}
                            </label>
                            <input
                                type="text"
                                id="coordinates-input"
                                name="coordinates"
                                wire:model="coordinates"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 text-slate-900 text-xs shadow-xs focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                placeholder="30.0444, 31.2357"
                            />
                        </div>
                    </div>
                </div>

                <!-- Row 4: Type, Language, Online Link -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label for="type" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Meeting Type') }} <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="type"
                            wire:model="type"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            required
                        >
                            <option value="in_person">{{ __('In-Person / حضوري') }}</option>
                            <option value="online">{{ __('Online / عبر الإنترنت') }}</option>
                            <option value="hybrid">{{ __('Hybrid / هجين') }}</option>
                        </select>
                        @error('type') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="language" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Language') }} <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="language"
                            wire:model="language"
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                            required
                        >
                            <option value="ar">{{ __('Arabic / العربية') }}</option>
                            <option value="en">{{ __('English / الإنجليزية') }}</option>
                            <option value="bilingual">{{ __('Bilingual / ثنائي اللغة') }}</option>
                        </select>
                        @error('language') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="online_link" class="block text-sm font-medium text-slate-700 mb-1">
                            {{ __('Online Meeting Link (if any)') }}
                        </label>
                        <input
                            type="url"
                            id="online_link"
                            wire:model="online_link"
                            placeholder="https://zoom.us/j/..."
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                        />
                        @error('online_link') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Row 5: Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">
                        {{ __('Notes / Accessibility / Directions') }}
                    </label>
                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="3"
                        class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-xs"
                        placeholder="e.g. Ring buzzer for room 4B. Wheelchair accessible."
                    ></textarea>
                    @error('notes') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <span class="text-xs text-slate-500">
                        {{ __('Submitting updates changes in real-time across the platform.') }}
                    </span>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm transition-colors shadow-xs disabled:opacity-50 cursor-pointer"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ __('Save & Publish / حفظ ونشر') }}
                        </span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            {{ __('Saving & Publishing...') }}
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Conflict Resolution Modal -->
        @if ($showConflictModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="bg-white rounded-2xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-rose-200">
                    <div class="flex items-center gap-3 text-rose-600 mb-3">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-lg font-bold text-slate-900">
                            {{ __('Concurrent Edit Conflict') }}
                        </h3>
                    </div>

                    <p class="text-sm text-slate-600 mb-4">
                        {{ $conflictMessage ?? __('Another servant updated this meeting while you were editing. To prevent accidental data loss, review the differences below.') }}
                    </p>

                    @if(!empty($conflictDiffs))
                        <div class="border border-slate-200 rounded-xl overflow-hidden mb-6">
                            <div class="bg-slate-50 px-4 py-2 border-b border-slate-200 text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                {{ __('Detected Field Differences') }}
                            </div>
                            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                                @foreach($conflictDiffs as $field => $diff)
                                    <div class="p-3 text-xs space-y-1.5">
                                        <div class="font-bold text-slate-800">{{ $diff['label'] ?? ucfirst(str_replace('_', ' ', $field)) }}</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div class="bg-amber-50/70 border border-amber-200 p-2 rounded-lg text-amber-900">
                                                <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                                    {{ __('Saved By :user', ['user' => $diff['changed_by'] ?? 'Other Servant']) }}
                                                </span>
                                                <div class="font-mono text-xs mt-0.5 break-all">
                                                    {{ is_array($diff['current_server_value'] ?? $diff['new_value']) ? json_encode($diff['current_server_value'] ?? $diff['new_value']) : ($diff['current_server_value'] ?? $diff['new_value'] ?? '-') }}
                                                </div>
                                            </div>
                                            <div class="bg-indigo-50/70 border border-indigo-200 p-2 rounded-lg text-indigo-900">
                                                <span class="block text-[10px] font-bold uppercase tracking-wider text-indigo-700">
                                                    {{ __('Your Attempted Edit') }}
                                                </span>
                                                <div class="font-mono text-xs mt-0.5 break-all">
                                                    {{ is_array($diff['your_value']) ? json_encode($diff['your_value']) : ($diff['your_value'] ?? '-') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row justify-end gap-2 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="resolveConflictReload"
                            class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                        >
                            {{ __('Discard My Edits & Reload') }}
                        </button>
                        <button
                            type="button"
                            wire:click="resolveConflictOverwrite"
                            class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer"
                        >
                            {{ __('Overwrite with My Changes') }}
                        </button>
                        <button
                            type="button"
                            wire:click="$set('showConflictModal', false)"
                            class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg text-slate-500 hover:text-slate-700 cursor-pointer"
                        >
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Revision History Card -->
        @if($meetingId)
            <div class="mt-8 bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
                <livewire:service.meeting-revision-history :meeting-id="$meetingId" :key="'rev-history-'.$meetingId.'-'.$form_version" />
            </div>
        @endif
    @endif
</div>

<!-- Inline Leaflet script for pin dragging and confirmation -->
<script>
    (function () {
        function setupMeetingEditorMap() {
            const mapEl = document.getElementById('meeting-editor-map');
            if (!mapEl || mapEl._leaflet_map) {
                return;
            }

            if (typeof L === 'undefined') {
                setTimeout(setupMeetingEditorMap, 150);
                return;
            }

            const initialLat = parseFloat(mapEl.dataset.lat) || 30.0444;
            const initialLng = parseFloat(mapEl.dataset.lng) || 31.2357;

            const map = L.map(mapEl, {
                minZoom: 5,
                maxZoom: 18,
            }).setView([initialLat, initialLng], 13);
            mapEl._leaflet_map = map;

            L.tileLayer('/tiles/{z}/{x}/{y}.png', {
                minZoom: 5,
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            const marker = L.marker([initialLat, initialLng], {
                draggable: true,
                title: 'Click or drag pin to update coordinates'
            }).addTo(map);

            function updateInputs(lat, lng) {
                const latFixed = (typeof lat === 'number' ? lat : parseFloat(lat)).toFixed(7);
                const lngFixed = (typeof lng === 'number' ? lng : parseFloat(lng)).toFixed(7);
                const coordsStr = latFixed + ', ' + lngFixed;

                const latInput = document.getElementById('latitude-input');
                const lngInput = document.getElementById('longitude-input');
                const coordsInput = document.getElementById('coordinates-input');

                if (latInput) {
                    latInput.value = latFixed;
                    latInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (lngInput) {
                    lngInput.value = lngFixed;
                    lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (coordsInput) {
                    coordsInput.value = coordsStr;
                    coordsInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Call Livewire component directly if available
                const lwEl = mapEl.closest('[wire\\:id]');
                if (lwEl && window.Livewire) {
                    const comp = window.Livewire.find(lwEl.getAttribute('wire:id'));
                    if (comp) {
                        if (typeof comp.confirmPin === 'function') {
                            comp.confirmPin(parseFloat(latFixed), parseFloat(lngFixed));
                        } else if (typeof comp.set === 'function') {
                            comp.set('latitude', parseFloat(latFixed));
                            comp.set('longitude', parseFloat(lngFixed));
                            comp.set('coordinates', coordsStr);
                        }
                    }
                }
            }

            // Dragging marker updates coordinates
            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });

            // Clicking the map moves the marker and updates coordinates
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                updateInputs(e.latlng.lat, e.latlng.lng);
            });

            // Clicking the pin marker updates coordinates
            marker.on('click', function () {
                const pos = marker.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupMeetingEditorMap);
        } else {
            setupMeetingEditorMap();
        }

        document.addEventListener('livewire:navigated', setupMeetingEditorMap);
    })();
</script>
