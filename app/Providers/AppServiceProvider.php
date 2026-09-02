<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(Carbon::now()->addDays(30));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(90));

        Passport::tokensCan([
            'seller' => 'Access seller-specific API routes',
            'master-login' => 'Master login access',
        ]);

        RateLimiter::for('seller-auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->input('email', $request->ip()));
        });
    }
}
