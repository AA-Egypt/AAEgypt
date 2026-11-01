@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto py-8 text-start">
    <h1 class="text-3xl font-bold text-slate-900 mb-6">
        {{ $locale === 'ar' ? $page->title_ar : $page->title_en }}
    </h1>
    <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4">
        {!! \Illuminate\Support\Str::markdown($locale === 'ar' ? $page->body_ar : $page->body_en) !!}
    </div>
</div>
@endsection

