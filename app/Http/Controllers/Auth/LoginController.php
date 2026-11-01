<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Alias for displaying the login form.
     */
    public function showLoginForm(): View
    {
        return $this->create();
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (Auth::user()?->is_suspended === true) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This service position term has ended. Please contact your Web Administrator.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user?->must_change_password) {
            if (! $user->first_login_at) {
                $user->forceFill(['first_login_at' => now()])->save();
            }

            return redirect()->route('first-login.password');
        }

        if ($user && ! empty($user->totp_secret)) {
            $request->session()->forget('mfa_verified');

            return redirect()->route('mfa.challenge');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Alias for handling an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        return $this->store($request);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Alias for destroying an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        return $this->destroy($request);
    }
}
