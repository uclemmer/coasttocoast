<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Webhooks get no session and no CSRF token: the caller is Stripe's
        // server, and its proof of identity is the request signature. Loaded
        // here rather than inside web.php so the exemption is visible.
        then: function (): void {
            Route::middleware('api')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Appended globally rather than to `web`, so the webhook route and any
         * future API surface get them too. See the class docblock for why
         * there is no Content-Security-Policy (card 7.1, doc 10 D-7.1-a).
         */
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
