<?php

use App\Livewire\Service\MeetingEditor;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'mfa.enrolled'])->group(function () {
    Route::get('/service/meetings', MeetingEditor::class)->name('service.meetings');
});

