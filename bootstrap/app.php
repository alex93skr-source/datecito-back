<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureMembershipAccess;
use App\Http\Middleware\RecordActivity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'email.verified' => EnsureEmailIsVerified::class,
            'membership' => EnsureMembershipAccess::class,
        ]);

        // En producción la API vive detrás de Caddy (HTTPS) dentro de la red de Docker:
        // sin esto Laravel creería que las peticiones llegan por HTTP.
        $middleware->trustProxies(at: '*');

        $middleware->api(append: [SecurityHeaders::class, RecordActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Nunca registrar contraseñas, códigos ni tokens en los logs.
        $exceptions->dontFlash(['password', 'current_password', 'code', 'purchase_token']);
    })->create();
