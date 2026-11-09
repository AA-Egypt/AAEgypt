<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);

        if (in_array($segment, ['ar', 'en'])) {
            $locale = $segment;
        } elseif ($request->has('locale') && in_array($request->query('locale'), ['ar', 'en'])) {
            $locale = $request->query('locale');
            if ($request->hasSession()) {
                session(['locale' => $locale]);
            }
        } elseif ($request->hasSession() && session()->has('locale') && in_array(session('locale'), ['ar', 'en'])) {
            $locale = session('locale');
        } else {
            $locale = App::getLocale() ?: 'en';
        }

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
