<?php

use App\Http\Controllers\Seller\StoreUpdateController;
use App\Http\Middleware\ShopifyAuthMiddleware;
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
                ->prefix('api/marketplace/seller')
                ->group(base_path('routes/seller/auth.php'));

            Route::middleware('api')
                ->prefix('api/marketplace/seller')
                ->group(base_path('routes/seller/master-login.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/store.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/import.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/bulk.php'));

            // Account is registered after the other seller routes. PUT /{seller:uuid}
            // would otherwise match a later seller path.
            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace/seller')
                ->group(base_path('routes/seller/account.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/products.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/sections.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/policies.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/shipping.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/media.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/orders.php'));

            Route::middleware('api')
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/easypost.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/reviews.php'));

            Route::middleware('api')
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/chat.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/dashboard.php'));

            Route::middleware('api')
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/stripe-webhooks.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/finance.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->group(base_path('routes/seller/subscribe.php'));

            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace/misc')
                ->group(base_path('routes/misc.php'));

            // Registered last. POST /store/{uuid} would otherwise match
            // POST /store/section and POST /store/gallery.
            Route::middleware(['api', 'auth:marketplace'])
                ->prefix('api/marketplace')
                ->post('/store/{store:uuid}', StoreUpdateController::class)
                ->name('seller.store.update');

            Route::middleware(['api', 'shopify'])
                ->prefix('api/marketplace/shopify')
                ->group(base_path('routes/shopify.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app sits behind an AWS ALB that terminates TLS and forwards plain
        // HTTP internally. Without trusting its X-Forwarded-* headers, Laravel
        // thinks every request is insecure and generates http:// URLs even
        // when the client connected over https:// (e.g. the Swagger UI spec
        // fetch, password reset links, any absolute url()/route() call).
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'shopify' => ShopifyAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
