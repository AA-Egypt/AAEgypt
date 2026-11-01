<?php

use App\Http\Middleware\EnsureGroupAccess;
use App\Http\Middleware\EnsureMfaEnrolled;
use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StripPublicCookies;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'locale' => SetLocale::class,
            'set.locale' => SetLocale::class,
            'mfa.enrolled' => EnsureMfaEnrolled::class,
            'group.access' => EnsureGroupAccess::class,
            'ensure.group.access' => EnsureGroupAccess::class,
            'legacy.redirects' => HandleLegacyRedirects::class,
            'strip.public.cookies' => StripPublicCookies::class,
        ]);
        $middleware->prepend(StripPublicCookies::class);
        $middleware->prepend(HandleLegacyRedirects::class);
        $middleware->web(append: [
            SetLocale::class,
            EnsureMfaEnrolled::class,
        ]);
        $middleware->priority([
            StripPublicCookies::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            EnsureMfaEnrolled::class,
            Authenticate::class,
            Filament\Http\Middleware\Authenticate::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
