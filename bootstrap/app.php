<?php

use App\Http\Middleware\EnsureAdminCinemaScope;
use App\Http\Middleware\EnsureTrustedHost;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's TrustProxies global middleware runs first and reads
        // config/trustedproxy.php. Validate the resulting host afterwards so a
        // trusted proxy cannot supply an arbitrary X-Forwarded-Host value.
        $middleware->append(EnsureTrustedHost::class);

        $middleware->validateCsrfTokens(except: [
            'payments/zalopay/callback',
            'booking/store',
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RequireRole::class,
            'permission' => RequirePermission::class,
            'admin.cinema.scope' => EnsureAdminCinemaScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
