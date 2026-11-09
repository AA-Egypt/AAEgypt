@extends('layouts.public', ['title' => __('Sign In')])

@section('content')
<div class="min-h-[calc(100vh-19rem)] flex flex-col justify-center py-6 sm:py-10">
    <div class="w-full max-w-md mx-auto">
        <!-- Auth Card -->
        <div class="bg-white py-8 px-6 sm:px-10 shadow-xs rounded-2xl border border-slate-200/80">
            <!-- Header with Emblem & Titles -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-700 mb-4 ring-1 ring-emerald-600/10 shadow-2xs">
                    <img src="{{ asset('images/emblem.png') }}" alt="{{ config('app.name', 'A.A. Egypt') }}" class="w-10 h-10 object-contain">
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ __('Sign In') }}
                </h1>
                <p class="mt-2 text-sm text-slate-600">
                    {{ __('Trusted Servants & Service Position Portal') }}
                </p>
            </div>

            <!-- Login Form -->
            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <!-- Official Mailbox Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 text-start mb-1.5">
                        {{ __('Official Mailbox Address') }}
                    </label>
                    <div class="relative rounded-xl">
                        <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="gsr.maadi@aaegypt.org"
                            class="block w-full rounded-xl border {{ $errors->has('email') ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-300 text-slate-900 focus:border-emerald-600 focus:ring-emerald-500/20' }} ps-10 pe-4 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 transition shadow-2xs text-start"
                        >
                    </div>
                    @error('email')
                        <p role="alert" class="mt-1.5 text-xs text-rose-600 flex items-center gap-1.5 text-start font-medium">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 text-start mb-1.5">
                        {{ __('Password') }}
                    </label>
                    <div class="relative rounded-xl">
                        <div class="absolute inset-y-0 start-0 ps-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••••••"
                            class="block w-full rounded-xl border {{ $errors->has('password') ? 'border-rose-300 text-rose-900 focus:border-rose-500 focus:ring-rose-500/20' : 'border-slate-300 text-slate-900 focus:border-emerald-600 focus:ring-emerald-500/20' }} ps-10 pe-10 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 transition shadow-2xs text-start"
                        >
                        <button
                            type="button"
                            onclick="const p = document.getElementById('password'); const isPass = p.type === 'password'; p.type = isPass ? 'text' : 'password'; this.querySelector('.eye-open').classList.toggle('hidden'); this.querySelector('.eye-closed').classList.toggle('hidden');"
                            class="absolute inset-y-0 end-0 pe-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer"
                            aria-label="{{ __('Toggle password visibility') }}"
                        >
                            <svg class="w-4 h-4 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="w-4 h-4 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p role="alert" class="mt-1.5 text-xs text-rose-600 flex items-center gap-1.5 text-start font-medium">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <!-- Remember me -->
                <div class="flex items-center justify-between pt-1">
                    <label for="remember" class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input
                            id="remember"
                            type="checkbox"
                            name="remember"
                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 transition cursor-pointer"
                        >
                        <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-sm shadow-xs hover:shadow transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 cursor-pointer"
                    >
                        <span>{{ __('Log in') }}</span>
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Security & Service Portal Notice -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-start">
                <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/60 text-xs text-slate-500">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <p class="leading-relaxed">
                        {{ __('Official service accounts require two-factor authentication (TOTP) and a secure 12+ character password. Authentication attempts are rate limited.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
