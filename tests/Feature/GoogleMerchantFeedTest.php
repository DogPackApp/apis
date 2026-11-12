<?php

use App\Models\Misc\Country;
use App\Models\Product\Item;
use App\Models\Product\ItemPrice;
use App\Models\Seller\OnboardingStatus;
use App\Models\Store\ShippingZone;
use App\Models\Store\ShippingZoneCountry;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
    Cache::flush();
});

test('the local google product feed includes an active priced product', function () {
    Country::query()->create([
        'id' => 38,
        'short_name' => 'CA',
        'name' => 'Canada',
        'currency_code' => 'CAD',
    ]);

    $store = Store::factory()->create([
        'country_id' => 38,
        'states' => Store::STATES_ACTIVE,
        'slug' => 'merchant-store',
    ]);

    OnboardingStatus::query()->create([
        'seller_id' => $store->seller_id,
        'store_id' => $store->id,
        'is_product' => 1,
        'is_shipping' => 1,
        'is_store_setting' => 1,
        'is_finance' => 1,
        'is_subscribe' => 1,
    ]);

    $zone = ShippingZone::query()->create([
        'store_id' => $store->id,
        'name' => 'Canada',
    ]);
    $zoneCountry = ShippingZoneCountry::query()->create([
        'shipping_zone_id' => $zone->id,
    ]);
    $zoneCountry->forceFill(['country_id' => 38])->save();

    $item = Item::factory()->create([
        'store_id' => $store->id,
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
        'title' => 'Merchant toy',
        'slug' => 'merchant-toy',
    ]);

    ItemPrice::query()->create([
        'item_id' => $item->id,
        'domestic_price' => 1000,
        'stock_quantity' => 4,
    ]);

    $path = storage_path('app/feeds/google-products.xml');

    $this->artisan('marketplace:generate-google-feed', [
        '--country' => 'CA',
        '--local' => true,
    ])->assertSuccessful();

    $xml = file_get_contents($path);
    @unlink($path);

    expect($xml)->toContain('Merchant toy')
        ->toContain('10.00 CAD');
});

test('an empty subscribe and save promotions file is still written locally', function () {
    Country::query()->create([
        'id' => 38,
        'short_name' => 'CA',
        'name' => 'Canada',
        'currency_code' => 'CAD',
    ]);

    $store = Store::factory()->create([
        'country_id' => 38,
        'states' => Store::STATES_ACTIVE,
    ]);

    OnboardingStatus::query()->create([
        'seller_id' => $store->seller_id,
        'store_id' => $store->id,
        'is_product' => 1,
        'is_shipping' => 1,
        'is_store_setting' => 1,
        'is_finance' => 1,
        'is_subscribe' => 1,
    ]);

    $zone = ShippingZone::query()->create([
        'store_id' => $store->id,
        'name' => 'Canada',
    ]);
    $zoneCountry = ShippingZoneCountry::query()->create([
        'shipping_zone_id' => $zone->id,
    ]);
    $zoneCountry->forceFill(['country_id' => 38])->save();

    Config::set('app.env', 'docker-local');

    $this->artisan('marketplace:generate-google-promotions', ['--country' => 'CA'])
        ->expectsOutputToContain('0 promotions')
        ->assertSuccessful();

    $path = storage_path('app/temp/google-promotions-ca.xml');
    expect(is_file($path))->toBeTrue();
    @unlink($path);
});
