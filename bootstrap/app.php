<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['api', 'throttle:seller-auth'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/auth.php'));

            // Store is registered before account. PUT /api/seller/{seller:uuid} would
            // otherwise match PUT /api/seller/store.
            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/store.php'));

            // Import and bulk are registered before account. PUT /api/seller/{seller:uuid}
            // would otherwise match PUT /api/seller/bulk.
            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/import.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/bulk.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/account.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/products.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/sections.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/policies.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/shipping.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/media.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/seller')
                ->group(base_path('routes/seller/orders.php'));

            Route::middleware('api')
                ->prefix('api/seller')
                ->group(base_path('routes/seller/easypost.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/misc')
                ->group(base_path('routes/misc.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app sits behind an AWS ALB that terminates TLS and forwards plain
        // HTTP internally. Without trusting its X-Forwarded-* headers, Laravel
        // thinks every request is insecure and generates http:// URLs even
        // when the client connected over https:// (e.g. the Swagger UI spec
        // fetch, password reset links, any absolute url()/route() call).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
