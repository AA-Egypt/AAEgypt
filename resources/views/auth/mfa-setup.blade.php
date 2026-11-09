@extends('layouts.public', ['title' => __('Set Up Two-Factor Authentication')])

@section('content')
<div class="min-h-[calc(100vh-19rem)] flex flex-col justify-center py-6 sm:py-10">
    <div class="w-full max-w-lg mx-auto">
        <!-- Auth Card -->
        <div class="bg-white py-8 px-6 sm:px-10 shadow-xs rounded-2xl border border-slate-200/80">
            <!-- Header with Key/Shield Icon & Titles -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-700 mb-4 ring-1 ring-emerald-600/10 shadow-2xs">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ __('Set Up Two-Factor Authentication') }}
                </h1>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                    {{ __('Two-factor authentication (TOTP) is mandatory for this account. Scan the QR code with your authenticator app (e.g., Google Authenticator, Authy) or enter the manual key below.') }}
                </p>
            </div>

            <!-- QR Code Section -->
            <div class="flex justify-center mb-4">
                <div id="qr-code" class="p-3 bg-white rounded-2xl border-2 border-slate-200/80 shadow-2xs flex items-center justify-center">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            <!-- Manual Key Section -->
            <div id="manual-key" class="mb-6 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center">
                <p class="text-xs text-slate-600">
                    <strong class="text-slate-800">{{ __('Manual Setup Key:') }}</strong>
                    <code class="ms-1.5 px-2 py-1 bg-white border border-slate-300 rounded-md text-emerald-800 font-mono text-xs sm:text-sm font-bold tracking-wider select-all">{{ $secret }}</code>
                </p>
            </div>

            <!-- Verification Code Form -->
            <form method="POST" action="{{ route('mfa.setup') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="code" class="block text-sm font-medium text-slate-700 text-start mb-1.5">
                        {{ __('6-digit Authenticator Code') }}
                    </label>
                    <div class="relative rounded-xl">
                        <input
                            id="code"
                            type="text"
                            name="code"
                            required
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            placeholder="123456"
                            autocomplete="one-time-code"
                            autofocus
                            class="block w-full text-center font-mono text-2xl tracking-[0.4em] py-3 rounded-xl border {{ $errors->has('code') ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-300 text-slate-900 focus:border-emerald-600 focus:ring-emerald-500/20' }} placeholder:text-slate-300 placeholder:tracking-widest focus:outline-none focus:ring-2 transition shadow-2xs"
                        >
                    </div>
                    @error('code')
                        <p role="alert" class="mt-2 text-xs text-rose-600 flex items-center justify-center gap-1.5 font-medium">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div>
                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm shadow-xs hover:shadow transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 cursor-pointer"
                    >
                        <span>{{ __('Confirm & Enable 2FA') }}</span>
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Sign in as another user -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 hover:text-emerald-700 transition font-medium underline underline-offset-4 cursor-pointer">
                        {{ __('Sign in with another account') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
