<?php

use App\Http\Middleware\ApiAuth;
use App\Http\Middleware\ApplyUserTheme;
use App\Http\Middleware\RequireAnyRole;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Cloudflare Tunnel and other reverse proxies so that
        // X-Forwarded-Proto/Host/For headers are honored.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'api.auth' => ApiAuth::class,
            'permission' => RequirePermission::class,
            'role' => RequireAnyRole::class,
        ]);

        // API routes need session support for cookie-based auth in MVP.
        $middleware->api(prepend: [
            StartSession::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
        ]);

        // Apply light/dark theme to all HTML responses before paint.
        $middleware->web(append: [
            ApplyUserTheme::class,
            SetLocale::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
