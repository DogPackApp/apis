<?php

namespace App\Providers;

use App\Models\Store\Store;
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
    public function register(): void
    {
        // Same lookup backend-api does for marketplace routes: the authenticated
        // seller's store, resolved when a controller asks for Store.
        $this->app->bind(Store::class, function (): ?Store {
            $seller = auth()->guard('marketplace')->user();

            if (! $seller) {
                return null;
            }

            return Store::query()
                ->select(['id', 'seller_id', 'uuid', 'name', 'slug', 'status', 'image', 'cover_image', 'stripe_status', 'stripe_account_id', 'timezone'])
                ->where('seller_id', $seller->id)
                ->first();
        });
    }

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
