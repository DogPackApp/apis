<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::tokensExpireIn(Carbon::now()->addDays(365));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(30));

        Passport::tokensCan([
            'seller' => 'Access seller-specific API routes',
            'master-login' => 'Master login access',
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
