<?php

namespace App\Providers;

use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Observers\Product\ItemObserver;
use Carbon\Carbon;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
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
        $this->app->singleton(Client::class, function (): Client {
            $builder = ClientBuilder::create();

            if (config('elasticsearch.cloud_id')) {
                $builder->setElasticCloudId(config('elasticsearch.cloud_id'));
            }

            if (config('elasticsearch.api_key')) {
                $builder->setApiKey(config('elasticsearch.api_key'));
            }

            return $builder->build();
        });

        $this->app->bind(Store::class, function (): ?Store {
            $seller = auth()->guard('marketplace')->user();

            if (! $seller) {
                return null;
            }

            return Store::query()
                ->select(['id', 'seller_id', 'uuid', 'name', 'slug', 'status', 'image', 'cover_image', 'stripe_status', 'stripe_account_id', 'stripe_customer_id', 'pm_type', 'pm_last_four', 'country_id', 'timezone'])
                ->where('seller_id', $seller->id)
                ->first();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Item::observe(ItemObserver::class);

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
