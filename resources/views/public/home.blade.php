@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto py-12 text-start">
    <h1 class="text-4xl font-extrabold text-slate-900 mb-4">
        {{ config('app.name', 'A.A. Egypt') }}
    </h1>
    <p class="text-lg text-slate-600 mb-8 leading-relaxed">
        {{ __('AA Declaration') }}
    </p>
    <div class="flex flex-wrap gap-4">
        <a href="{{ url(app()->getLocale() . '/who-we-are') }}" class="px-5 py-2.5 bg-emerald-600 text-white rounded-md font-medium hover:bg-emerald-700 transition">
            {{ __('Who We Are') }}
        </a>
        <a href="{{ url(app()->getLocale() . '/privacy') }}" class="px-5 py-2.5 bg-white border border-slate-300 text-slate-700 rounded-md font-medium hover:bg-slate-50 transition">
            {{ __('Privacy Policy') }}
        </a>
    </div>
</div>
@endsection

