<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripPublicCookies
{
    /**
     * Path patterns that belong to administrative or service portal areas.
     */
    protected array $serviceRoutePatterns = [
        'admin',
        'admin/*',
        'service',
        'service/*',
        'app-passwords',
        'app-passwords/*',
        'login',
        'logout',
        'mfa-challenge',
        'mfa-setup',
        'mfa/challenge',
        'mfa/setup',
        'mfa/verify',
        'mfa*',
        'first-login-password',
        'first-login/*',
        'first-login*',
        'groups',
        'groups/*',
        'documents',
        'documents/*',
        'dashboard',
        'dashboard/*',
        'livewire',
        'livewire/*',
        '_boost',
        '_boost/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldStripCookies($request)) {
            // Completely remove the Set-Cookie header
            $response->headers->remove('Set-Cookie');

            // Remove all queued/attached cookies from the ResponseHeaderBag
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }

            // Ensure zero tracking headers are emitted
            $response->headers->remove('X-Tracking-Id');
            $response->headers->remove('X-Visitor-Id');
            $response->headers->remove('X-Session-Id');
        }

        return $response;
    }

    /**
     * Determine whether cookies must be stripped for this request.
     * Public pages must emit ZERO cookies and ZERO tracking headers.
     */
    public function shouldStripCookies(Request $request): bool
    {
        return ! $this->isServiceOrAdminRoute($request);
    }

    /**
     * Check if the incoming request targets an admin or service portal route.
     */
    protected function isServiceOrAdminRoute(Request $request): bool
    {
        if ($request->is($this->serviceRoutePatterns)) {
            return true;
        }

        // Meeting management mutation routes (PUT, PATCH, DELETE meetings/{meeting})
        if (! $request->isMethodSafe() && ($request->is('meetings') || $request->is('meetings/*'))) {
            return true;
        }

        return false;
    }
}
