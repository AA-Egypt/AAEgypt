@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto py-8 text-start">
    <h1 class="text-3xl font-bold text-slate-900 mb-6">{{ __('Who We Are') }}</h1>
    <div class="prose max-w-none text-slate-700 leading-relaxed space-y-4">
        @if(app()->getLocale() === 'ar')
            <p>زمالة مدمني الخمر المجهولين هي زمالة تضم رجالاً ونساءً يشاركون بعضهم البعض خبراتهم وقوتهم وأملهم لحل مشكلتهم المشتركة ومساعدة الآخرين على التعافي من إدمان الخمر.</p>
        @else
            <p>Alcoholics Anonymous is a fellowship of people who share their experience, strength and hope with each other that they may solve their common problem and help others to recover from alcoholism.</p>
        @endif
    </div>
</div>
@endsection

