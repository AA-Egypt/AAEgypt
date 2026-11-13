@extends('layouts.public', ['title' => __('DMS Audit Logs')])

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Banner -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        {{ __('Append-Only Log') }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 font-mono text-[11px]">
                        0640 (rw-r-----)
                    </span>
                    <span id="live-indicator" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        <span id="live-text">{{ __('Live Stream') }}</span>
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-2">{{ __('DMS Audit Logs') }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    {{ __('Immutable audit trail of all DMS document events recorded in JSONL format.') }}
                    <span class="block sm:inline font-mono text-xs text-slate-400 mt-0.5 sm:mt-0 sm:ml-2">({{ $logPath }})</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="toggle-stream-btn" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span id="stream-btn-text">{{ __('Auto-Refresh: ON') }}</span>
                </button>
                <button type="button" id="refresh-now-btn" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ __('Poll Stream Now') }}
                </button>
                <a href="{{ route('service.documents.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back to DMS') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-5">
        <form method="GET" action="{{ route('admin.dms.audit-logs') }}" id="audit-filter-form" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Date From -->
                <div>
                    <label for="date_from" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Date From') }}</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                           class="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-xs">
                </div>

                <!-- Date To -->
                <div>
                    <label for="date_to" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Date To') }}</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                           class="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-xs">
                </div>

                <!-- Action / Event Filter -->
                <div>
                    <label for="event" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('Action / Event') }}</label>
                    <select id="event" name="event" class="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-xs">
                        <option value="">{{ __('All Actions') }}</option>
                        @foreach($actions as $actionKey => $actionLabel)
                            <option value="{{ $actionKey }}" {{ ($filters['event'] ?? '') === $actionKey ? 'selected' : '' }}>
                                {{ __($actionLabel) }} ({{ $actionKey }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- User Filter -->
                <div>
                    <label for="user" class="block text-xs font-semibold text-slate-700 mb-1">{{ __('User Filter') }}</label>
                    <input type="text" id="user" name="user" value="{{ $filters['user'] ?? '' }}"
                           placeholder="{{ __('Email or User ID...') }}"
                           class="w-full text-sm rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 shadow-xs">
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <div class="text-xs text-slate-500">
                    {{ __('Showing') }} <span class="font-semibold text-slate-700" id="total-count-badge">{{ $totalCount }}</span> {{ __('matching events') }}
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.dms.audit-logs') }}" class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-slate-900">
                        {{ __('Reset Filters') }}
                    </a>
                    <button type="submit" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition">
                        {{ __('Apply Filters') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Audit Log Records Stream Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('Audit Events Stream') }}
            </h2>
            <div class="text-xs text-slate-400 font-mono">
                {{ __('Format: JSONL') }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-700 font-semibold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('Timestamp (ISO8601)') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Action') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('User') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Group') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Document') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('SHA256') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('IP Address') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body" class="divide-y divide-slate-100 font-normal">
                    @forelse($logs as $log)
                        @php
                            $event = $log['event'] ?? 'unknown';
                            $status = $log['status'] ?? 'clean';
                            $eventClasses = match($event) {
                                'upload' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'download' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'delete' => 'bg-rose-100 text-rose-800 border-rose-200',
                                'scan_pass' => 'bg-green-100 text-green-800 border-green-200',
                                'scan_fail' => 'bg-red-100 text-red-800 border-red-200',
                                'version_bump' => 'bg-purple-100 text-purple-800 border-purple-200',
                                'permission_change' => 'bg-amber-100 text-amber-800 border-amber-200',
                                default => 'bg-slate-100 text-slate-800 border-slate-200',
                            };
                            $statusClasses = match($status) {
                                'clean' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'infected', 'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                'processing' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition audit-row">
                            <td class="px-4 py-3 font-mono whitespace-nowrap text-slate-700">
                                {{ $log['timestamp'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $eventClasses }}">
                                    {{ $event }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $log['user_email'] ?? 'System/Guest' }}</div>
                                @if(!empty($log['user_id']))
                                    <div class="text-[10px] text-slate-400 font-mono">UID: {{ $log['user_id'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-slate-500">
                                {{ $log['group_id'] ? '#'.$log['group_id'] : '-' }}
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <div class="font-medium text-slate-800 truncate" title="{{ $log['doc_name'] ?? '' }}">
                                    {{ $log['doc_name'] ?? '-' }}
                                </div>
                                @if(!empty($log['doc_id']))
                                    <div class="text-[10px] text-slate-400 font-mono truncate" title="{{ $log['doc_id'] }}">
                                        {{ $log['doc_id'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                @if(!empty($log['sha256']))
                                    <span title="{{ $log['sha256'] }}">{{ substr($log['sha256'], 0, 10) }}...</span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">
                                {{ $log['ip'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $statusClasses }}">
                                    {{ $status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-10 h-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="font-semibold text-slate-700">{{ __('No audit events found') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">{{ __('Audit events will appear here as document actions occur.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const streamToggleBtn = document.getElementById('toggle-stream-btn');
    const refreshNowBtn = document.getElementById('refresh-now-btn');
    const streamBtnText = document.getElementById('stream-btn-text');
    const liveIndicator = document.getElementById('live-indicator');
    const liveText = document.getElementById('live-text');
    let autoRefresh = true;
    let refreshInterval = null;

    function fetchStreamData() {
        const form = document.getElementById('audit-filter-form');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.append('format', 'json');

        fetch(`{{ route('admin.dms.audit-logs') }}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('total-count-badge').textContent = data.total;
                renderAuditRows(data.data);
            }
        })
        .catch(err => console.error('Audit stream polling error:', err));
    }

    function renderAuditRows(records) {
        const tbody = document.getElementById('audit-table-body');
        if (!records || records.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">
                <p class="font-semibold text-slate-700">{{ __('No audit events found') }}</p>
            </td></tr>`;
            return;
        }

        const getEventBadge = (event) => {
            const classes = {
                'upload': 'bg-blue-100 text-blue-800 border-blue-200',
                'download': 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'delete': 'bg-rose-100 text-rose-800 border-rose-200',
                'scan_pass': 'bg-green-100 text-green-800 border-green-200',
                'scan_fail': 'bg-red-100 text-red-800 border-red-200',
                'version_bump': 'bg-purple-100 text-purple-800 border-purple-200',
                'permission_change': 'bg-amber-100 text-amber-800 border-amber-200',
            }[event] || 'bg-slate-100 text-slate-800 border-slate-200';
            return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border ${classes}">${event}</span>`;
        };

        const getStatusBadge = (status) => {
            const classes = {
                'clean': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'infected': 'bg-red-50 text-red-700 border-red-200',
                'rejected': 'bg-red-50 text-red-700 border-red-200',
                'processing': 'bg-yellow-50 text-yellow-700 border-yellow-200',
            }[status] || 'bg-slate-50 text-slate-700 border-slate-200';
            return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border ${classes}">${status || 'clean'}</span>`;
        };

        tbody.innerHTML = records.map(r => `
            <tr class="hover:bg-slate-50/80 transition audit-row">
                <td class="px-4 py-3 font-mono whitespace-nowrap text-slate-700">${r.timestamp || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap">${getEventBadge(r.event || 'unknown')}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-900">${r.user_email || 'System/Guest'}</div>
                    ${r.user_id ? `<div class="text-[10px] text-slate-400 font-mono">UID: ${r.user_id}</div>` : ''}
                </td>
                <td class="px-4 py-3 whitespace-nowrap font-mono text-slate-500">${r.group_id ? '#' + r.group_id : '-'}</td>
                <td class="px-4 py-3 max-w-xs">
                    <div class="font-medium text-slate-800 truncate" title="${r.doc_name || ''}">${r.doc_name || '-'}</div>
                    ${r.doc_id ? `<div class="text-[10px] text-slate-400 font-mono truncate" title="${r.doc_id}">${r.doc_id}</div>` : ''}
                </td>
                <td class="px-4 py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                    ${r.sha256 ? `<span title="${r.sha256}">${r.sha256.substring(0, 10)}...</span>` : '-'}
                </td>
                <td class="px-4 py-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">${r.ip || '-'}</td>
                <td class="px-4 py-3 whitespace-nowrap">${getStatusBadge(r.status)}</td>
            </tr>
        `).join('');
    }

    function startPolling() {
        if (!refreshInterval) {
            refreshInterval = setInterval(fetchStreamData, 5000);
        }
        liveIndicator.classList.remove('opacity-50');
        liveText.textContent = '{{ __("Live Stream") }}';
        streamBtnText.textContent = '{{ __("Auto-Refresh: ON") }}';
    }

    function stopPolling() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        liveIndicator.classList.add('opacity-50');
        liveText.textContent = '{{ __("Paused") }}';
        streamBtnText.textContent = '{{ __("Auto-Refresh: OFF") }}';
    }

    if (streamToggleBtn) {
        streamToggleBtn.addEventListener('click', function () {
            autoRefresh = !autoRefresh;
            if (autoRefresh) {
                startPolling();
            } else {
                stopPolling();
            }
        });
    }

    if (refreshNowBtn) {
        refreshNowBtn.addEventListener('click', function () {
            fetchStreamData();
        });
    }

    // Start live auto-polling by default
    startPolling();
});
</script>
@endpush
@endsection
