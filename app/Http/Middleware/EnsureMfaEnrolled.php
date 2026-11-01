<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaEnrolled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->is('service*', 'admin*', 'app-passwords*') || $request->routeIs('service.*', 'admin.*', 'app-passwords.*')) {
                return redirect()->guest(route('login'));
            }

            return $next($request);
        }

        // Exempt setup routes, auth routes, and logout to prevent redirect loops
        if ($request->is('first-login*', 'mfa*', 'logout', 'up', '_boost*', 'admin/access-matrix*') ||
            $request->routeIs('first-login.*', 'mfa.*', 'logout', 'admin.access-matrix.*')) {
            return $next($request);
        }

        // 24-hour setup limit from first sign-in attempt
        if ($user->first_login_at &&
            $user->first_login_at->copy()->addHours(24)->isPast() &&
            ($user->must_change_password || empty($user->totp_secret))) {
            abort(403, 'The 24-hour setup limit has expired. Please contact your Web Administrator.');
        }

        // Step 1: User is forced to change password to a personal one
        if ($user->must_change_password && ! $user->password_changed_at && ! session('password_changed')) {
            return redirect()->route('first-login.password');
        }

        // Step 2: User is forced to enroll TOTP
        if (empty($user->totp_secret)) {
            if ($user->must_change_password || session('password_changed') || $request->is('service*', 'admin*', 'app-passwords*') || $request->routeIs('service.*', 'admin.*', 'app-passwords.*')) {
                return redirect()->route('mfa.setup');
            }
        }

        // Step 3: Subsequent logins require TOTP verification every time for protected routes
        if (! empty($user->totp_secret) && ! session('mfa_verified')) {
            if ($request->is('service*', 'admin*', 'app-passwords*') || $request->routeIs('service.*', 'admin.*', 'app-passwords.*')) {
                return redirect()->route('mfa.challenge');
            }
        }

        return $next($request);
    }
}
