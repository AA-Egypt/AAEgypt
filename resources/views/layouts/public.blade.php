<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name', 'A.A. Egypt') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/emblem.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/emblem.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800 antialiased">
    @include('partials.header')

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>

