<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
        <div>
            <h3 class="text-lg font-bold text-slate-900">
                {{ __('Revision History & 1-Click Restore') }}
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">
                {{ __('Every edit is recorded permanently. You can restore any past version at any time.') }}
            </p>
        </div>
        @if($meeting)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                {{ __('Current Version: v') }}{{ $meeting->version }}
            </span>
        @endif
    </div>

    <!-- Flash notifications -->
    @if (session()->has('message') || session()->has('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('message') ?? session('success') }}</span>
        </div>
    @endif

    <!-- Diff Preview Modal/Card -->
    @if($diffPreview !== null)
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    {{ __('Diff for Revision #') }}{{ $selectedRevisionId }}
                </h4>
                <button
                    type="button"
                    wire:click="clearPreview"
                    class="text-xs text-slate-500 hover:text-slate-800 font-medium"
                >
                    {{ __('Close Diff') }} &times;
                </button>
            </div>

            @if(empty($diffPreview))
                <p class="text-xs text-slate-500 italic">{{ __('No field differences recorded in this revision snapshot.') }}</p>
            @else
                <div class="divide-y divide-slate-200/60 text-xs">
                    @foreach($diffPreview as $field => $data)
                        <div class="py-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <span class="font-medium text-slate-800">{{ $data['label'] ?? $field }}:</span>
                            <div class="bg-rose-50 text-rose-800 p-1.5 rounded font-mono text-[11px] truncate">
                                <span class="text-[9px] uppercase font-bold text-rose-500 block">{{ __('Before') }}</span>
                                {{ is_array($data['old']) ? json_encode($data['old']) : ($data['old'] ?? '-') }}
                            </div>
                            <div class="bg-emerald-50 text-emerald-800 p-1.5 rounded font-mono text-[11px] truncate">
                                <span class="text-[9px] uppercase font-bold text-emerald-500 block">{{ __('After') }}</span>
                                {{ is_array($data['new']) ? json_encode($data['new']) : ($data['new'] ?? '-') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Revisions List -->
    @if($revisions->isEmpty())
        <div class="p-6 text-center rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-xs">
            {{ __('No previous revisions recorded yet for this meeting. Subsequent saves will appear here.') }}
        </div>
    @else
        <div class="relative border-l-2 border-slate-200 ms-3 space-y-6">
            @foreach($revisions as $rev)
                <div class="ms-6 relative group">
                    <!-- Dot indicator -->
                    <span class="absolute -start-[31px] top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-white ring-4 ring-slate-100 {{ $meeting && (int)$meeting->version === (int)$rev->version ? 'bg-indigo-600 ring-indigo-100' : 'bg-slate-400' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $meeting && (int)$meeting->version === (int)$rev->version ? 'bg-white' : 'bg-white' }}"></span>
                    </span>

                    <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs hover:border-slate-300 transition">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold font-mono {{ $meeting && (int)$meeting->version === (int)$rev->version ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700' }}">
                                    v{{ $rev->version }}
                                </span>
                                @if($meeting && (int)$meeting->version === (int)$rev->version)
                                    <span class="text-[10px] font-semibold text-indigo-600 uppercase tracking-wider bg-indigo-50 px-1.5 py-0.5 rounded">
                                        {{ __('Current Live') }}
                                    </span>
                                @endif
                                <span class="text-xs text-slate-600">
                                    {{ __('by') }} <strong>{{ $rev->user?->name ?? __('System / Servant') }}</strong>
                                </span>
                            </div>
                            <span class="text-xs text-slate-400">
                                {{ $rev->created_at ? $rev->created_at->format('M j, Y g:i A') : '' }}
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                wire:click="previewDiff({{ $rev->id }})"
                                class="text-xs text-slate-600 hover:text-slate-900 font-medium inline-flex items-center gap-1 cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                {{ __('Inspect Diff') }}
                            </button>

                            <button
                                type="button"
                                wire:click="restore({{ $rev->id }})"
                                wire:confirm="{{ __('Are you sure you want to restore meeting data back to version :version? This will publish the restored state immediately.', ['version' => $rev->version]) }}"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold inline-flex items-center gap-1 ms-auto cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ __('1-Click Restore') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
