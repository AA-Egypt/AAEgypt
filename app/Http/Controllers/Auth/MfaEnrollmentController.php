<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MfaEnrollmentController extends Controller
{
    /**
     * Check if the 24-hour setup limit from first sign-in attempt has expired.
     */
    protected function checkSetupLimit(User $user): void
    {
        if ($user->first_login_at &&
            $user->first_login_at->copy()->addHours(24)->isPast() &&
            ($user->must_change_password || empty($user->totp_secret))) {
            abort(403, 'The 24-hour setup limit has expired. Please contact your Web Administrator.');
        }
    }

    /**
     * Display the first-login password change form.
     */
    public function showPasswordForm(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->checkSetupLimit($user);

        if (! $user->first_login_at) {
            $user->forceFill(['first_login_at' => now()])->save();
        }

        if (! $user->must_change_password || $user->password_changed_at || $request->session()->get('password_changed')) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.first-login-password');
    }

    /**
     * Update the initial password to a personal one (min 12 chars).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->checkSetupLimit($user);

        $rules = [
            'password' => ['required', 'string', 'min:12'],
        ];

        if ($request->has('password_confirmation')) {
            $rules['password'][] = 'confirmed';
        }

        $validated = $request->validate($rules);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        $request->session()->put('password_changed', true);

        return redirect()->route('mfa.setup');
    }

    /**
     * Display the MFA setup form with offline SVG QR code and manual key.
     */
    public function showMfaSetup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->checkSetupLimit($user);

        if (! $user->first_login_at) {
            $user->forceFill(['first_login_at' => now()])->save();
        }

        // If user must still change password, force password change first
        if ($user->must_change_password && ! $user->password_changed_at && ! $request->session()->get('password_changed')) {
            return redirect()->route('first-login.password');
        }

        // If user is already fully enrolled, redirect away
        if (! empty($user->totp_secret) && ! $user->must_change_password) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $google2fa = app('pragmarx.google2fa');

        $secret = $request->session()->get('totp_setup_secret');
        if (! $secret) {
            $secret = $google2fa->generateSecretKey();
            $request->session()->put('totp_setup_secret', $secret);
        }

        $companyName = config('app.name', 'AA Egypt');
        $otpAuthUrl = $google2fa->getQRCodeUrl($companyName, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($otpAuthUrl);
        $qrCodeSvg = trim(preg_replace('/<\?xml.*?\?>/ms', '', $svg));

        return view('auth.mfa-setup', compact('qrCodeSvg', 'secret'));
    }

    /**
     * Confirm TOTP enrollment using a 6-digit code.
     */
    public function confirmMfaSetup(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->checkSetupLimit($user);

        if ($user->must_change_password && ! $user->password_changed_at && ! $request->session()->get('password_changed')) {
            return redirect()->route('first-login.password');
        }

        $code = (string) ($request->input('code') ?? $request->input('totp_code') ?? $request->input('one_time_password'));
        $secret = (string) ($request->input('secret') ?? $request->session()->get('totp_setup_secret'));

        if (empty($secret)) {
            return redirect()->route('mfa.setup')->withErrors(['code' => 'Setup session expired. Please refresh and try again.']);
        }

        if (empty($code) || strlen(trim($code)) !== 6) {
            return back()->withErrors(['code' => 'Please provide a valid 6-digit code.']);
        }

        $google2fa = app('pragmarx.google2fa');
        // Window tolerance of 1 step (30 seconds) clock drift
        $valid = $google2fa->verifyKey($secret, $code, 1);

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid TOTP code. Please try again.']);
        }

        // Store TOTP secret encrypted and clear onboarding flag
        $user->forceFill([
            'totp_secret' => Crypt::encryptString($secret),
            'must_change_password' => false,
        ])->save();

        $request->session()->forget('totp_setup_secret');
        $request->session()->forget('password_changed');
        $request->session()->put('mfa_verified', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
