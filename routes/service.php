<?php

use App\Livewire\Service\MeetingEditor;
use App\Livewire\Service\MeetingRevisionHistory;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'mfa.enrolled'])->group(function () {
    Route::get('/service/meetings', MeetingEditor::class)->name('service.meetings');
    Route::get('/service/meetings/{meeting}/history', MeetingRevisionHistory::class)->name('service.meetings.history');
});
