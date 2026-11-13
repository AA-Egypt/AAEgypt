<?php

use App\Http\Controllers\Service\DmsController;
use App\Http\Controllers\Service\DmsUploadController;
use App\Livewire\Service\MeetingEditor;
use App\Livewire\Service\MeetingRevisionHistory;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'mfa.enrolled'])->group(function () {
    Route::get('/service/meetings', MeetingEditor::class)->name('service.meetings');
    Route::get('/service/meetings/{meeting}/history', MeetingRevisionHistory::class)->name('service.meetings.history');

    // Document Management System (DMS) - aaegypt.org/service/documents
    Route::get('/service/documents', [DmsController::class, 'index'])->name('service.documents.index');
    Route::get('/service/documents/folders/{folder}', [DmsController::class, 'showFolder'])->name('service.documents.folder');
    Route::post('/service/documents/upload', [DmsController::class, 'upload'])->name('service.documents.upload');
    Route::get('/service/documents/{document}/download', [DmsController::class, 'download'])->name('service.documents.download');
    Route::delete('/service/documents/{document}', [DmsController::class, 'destroy'])->name('service.documents.destroy');

    // DMS Asynchronous Upload Intake & Queue Pipeline
    Route::post('/service/documents/uploads', [DmsUploadController::class, 'store'])->name('service.documents.uploads.store');
    Route::post('/service/documents/async-upload', [DmsUploadController::class, 'store'])->name('service.documents.async-upload');
    Route::post('/service/documents/intake', [DmsUploadController::class, 'store'])->name('service.documents.intake');
    Route::get('/service/documents/uploads/{document}', [DmsUploadController::class, 'show'])->name('service.documents.uploads.show');
    Route::get('/service/documents/uploads/{document}/status', [DmsUploadController::class, 'status'])->name('service.documents.uploads.status');

    // Aliases
    Route::get('/service/dms', [DmsController::class, 'index'])->name('service.dms.index');
});

// DMS subdomain routing - dms.aaegypt.org
Route::domain('dms.aaegypt.org')->middleware(['auth', 'mfa.enrolled'])->group(function () {
    Route::get('/', [DmsController::class, 'index'])->name('dms.home');
    Route::get('/folders/{folder}', [DmsController::class, 'showFolder'])->name('dms.folder');
    Route::post('/upload', [DmsController::class, 'upload'])->name('dms.upload');
    Route::get('/{document}/download', [DmsController::class, 'download'])->name('dms.download');
    Route::delete('/{document}', [DmsController::class, 'destroy'])->name('dms.destroy');
    Route::post('/uploads', [DmsUploadController::class, 'store'])->name('dms.uploads.store');
    Route::post('/async-upload', [DmsUploadController::class, 'store'])->name('dms.async-upload');
    Route::get('/uploads/{document}/status', [DmsUploadController::class, 'status'])->name('dms.uploads.status');
});
