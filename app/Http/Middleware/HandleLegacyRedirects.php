<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleLegacyRedirects
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        $redirect = $this->findRedirect($uri, $request);

        if ($redirect) {
            $redirect->increment('hit_count');

            return redirect($redirect->target_url, 301);
        }

        return $next($request);
    }

    /**
     * Find a matching Redirect model for the given URI and request.
     */
    protected function findRedirect(string $uri, Request $request): ?Redirect
    {
        try {
            // Check direct match on getRequestUri()
            $redirect = Redirect::where('source_path', $uri)->first();

            if ($redirect) {
                return $redirect;
            }

            // Check normalized variants
            $candidates = [];

            // Leading slash variations
            $candidates[] = '/'.ltrim($uri, '/');
            $candidates[] = ltrim($uri, '/');

            // Trailing slash variations
            if (str_contains($uri, '?')) {
                [$path, $query] = explode('?', $uri, 2);
                $candidates[] = rtrim($path, '/').'?'.$query;
                $candidates[] = '/'.ltrim(rtrim($path, '/'), '').'?'.$query;
            } else {
                $candidates[] = rtrim($uri, '/');
                $candidates[] = rtrim($uri, '/').'/';
                $candidates[] = '/'.trim($uri, '/');
            }

            // Path info without query
            $pathInfo = $request->getPathInfo();
            $candidates[] = $pathInfo;
            $candidates[] = rtrim($pathInfo, '/');
            $candidates[] = ltrim($pathInfo, '/');

            $candidates = array_values(array_filter(array_unique($candidates), fn ($item) => $item !== ''));

            return Redirect::whereIn('source_path', $candidates)->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
