@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto py-8 text-start">
    <h1 class="text-3xl font-bold text-slate-900 mb-6">{{ __('Meetings') }}</h1>
    <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4">
        @if(app()->getLocale() === 'ar')
            <p>دليل واجتماعات زمالة مدمني الخمر المجهولين في جمهورية مصر العربية. جميع الاجتماعات مجانية وسرية ومفتوحة للراغبين في التعافي.</p>
        @else
            <p>Directory and meetings of Alcoholics Anonymous in Egypt. All meetings are free, confidential, and open to anyone seeking recovery.</p>
        @endif
    </div>
</div>
@endsection

