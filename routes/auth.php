<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaEnrollmentController;
use App\Http\Controllers\Auth\MfaVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // First-access password reset
    Route::get('first-login-password', [MfaEnrollmentController::class, 'showPasswordForm'])->name('first-login.password');
    Route::post('first-login-password', [MfaEnrollmentController::class, 'updatePassword']);
    Route::get('first-login/password', [MfaEnrollmentController::class, 'showPasswordForm'])->name('first-login-password');
    Route::post('first-login/password', [MfaEnrollmentController::class, 'updatePassword']);

    // TOTP enrollment
    Route::get('mfa-setup', [MfaEnrollmentController::class, 'showMfaSetup'])->name('mfa.setup');
    Route::post('mfa-setup', [MfaEnrollmentController::class, 'confirmMfaSetup']);
    Route::get('mfa/setup', [MfaEnrollmentController::class, 'showMfaSetup'])->name('mfa-setup');
    Route::post('mfa/setup', [MfaEnrollmentController::class, 'confirmMfaSetup']);

    // Subsequent logins TOTP challenge
    Route::get('mfa-challenge', [MfaVerificationController::class, 'showChallenge'])->name('mfa.challenge');
    Route::post('mfa-challenge', [MfaVerificationController::class, 'verifyChallenge']);
    Route::get('mfa/challenge', [MfaVerificationController::class, 'showChallenge'])->name('mfa-challenge');
    Route::post('mfa/challenge', [MfaVerificationController::class, 'verifyChallenge']);
    Route::get('mfa/verify', [MfaVerificationController::class, 'showChallenge'])->name('mfa.verify');
    Route::post('mfa/verify', [MfaVerificationController::class, 'verifyChallenge']);
});
