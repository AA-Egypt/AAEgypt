<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class MfaVerificationController extends Controller
{
    /**
     * Display the TOTP challenge form.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (empty($user->totp_secret)) {
            return redirect()->route('mfa.setup');
        }

        if ($request->session()->get('mfa_verified')) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return view('auth.mfa-challenge');
    }

    /**
     * Verify the 6-digit TOTP challenge code.
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (empty($user->totp_secret)) {
            return redirect()->route('mfa.setup');
        }

        $code = (string) ($request->input('code') ?? $request->input('totp_code') ?? $request->input('one_time_password'));

        if (empty($code) || strlen(trim($code)) !== 6) {
            return back()->withErrors(['code' => 'Please provide a valid 6-digit code.']);
        }

        try {
            $secret = Crypt::decryptString($user->totp_secret);
        } catch (\Throwable $e) {
            $secret = $user->totp_secret;
        }

        $google2fa = app('pragmarx.google2fa');
        // Window tolerance of 1 step (30 seconds) clock drift
        $valid = $google2fa->verifyKey($secret, $code, 1);

        if (! $valid) {
            return back()->withErrors(['code' => 'The provided one-time password is invalid.']);
        }

        $request->session()->put('mfa_verified', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
