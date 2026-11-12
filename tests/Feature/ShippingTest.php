<?php

use App\Models\Misc\Country;
use App\Models\Seller\Seller;
use App\Models\Store\ShippingRate;
use App\Models\Store\Store;
use App\Services\OnboardingService;
use App\Services\Store\EasyPostService;
use EasyPost\Address;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function shippingSellerWithStore(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

function shippingCountry(): Country
{
    $country = new Country;
    $country->id = 231;
    $country->short_name = 'US';
    $country->name = 'United States';
    $country->save();

    return $country;
}

test('shipping routes require authentication', function () {
    $this->getJson('/api/seller/shipping/rates')->assertUnauthorized();
    $this->postJson('/api/seller/shipping', [])->assertUnauthorized();
    $this->getJson('/api/seller/shipping/shipping')->assertUnauthorized();
    $this->getJson('/api/seller/shipping/packaging')->assertUnauthorized();
});

test('seller sees global rates and can create a custom method', function () {
    [, $store] = shippingSellerWithStore();

    $global = new ShippingRate;
    $global->name = 'Ground';
    $global->description = 'Carrier ground';
    $global->save();

    $created = $this->postJson('/api/seller/shipping/custom', [
        'name' => 'Local drop off',
        'description' => 'Hand delivery',
    ])->assertCreated()
        ->assertJsonPath('name', 'Local drop off')
        ->assertJsonPath('description', 'Hand delivery')
        ->json('uuid');

    $this->putJson('/api/seller/shipping/custom/'.$created, [
        'name' => 'Local pickup',
        'description' => 'Counter',
    ])->assertOk()
        ->assertJsonPath('uuid', $created)
        ->assertJsonPath('name', 'Local pickup');

    $this->getJson('/api/seller/shipping/rates')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Ground'])
        ->assertJsonFragment(['name' => 'Local pickup']);

    expect(ShippingRate::query()->where('store_id', $store->id)->count())->toBe(1);
});

test('saving zones and packaging replaces the store shipping setup', function () {
    [$seller] = shippingSellerWithStore();
    (new OnboardingService)->initiateOnboarding($seller);
    $country = shippingCountry();

    $rate = new ShippingRate;
    $rate->name = 'Ground';
    $rate->description = 'Carrier ground';
    $rate->save();

    $this->postJson('/api/seller/shipping', [
        'shipping' => [[
            'name' => 'Domestic',
            'country' => [$country->id],
            'shipping_zone_rates' => [[
                'shipping_type' => 1,
                'shipping_rates' => ['value' => (string) $rate->uuid],
                'price' => 12,
                'condition_type' => 'PRICE',
                'low_value' => 10,
                'high_value' => 50,
            ]],
        ]],
        'packaging' => [[
            'name' => 'Mailer',
            'type' => 'BOX',
            'length' => 10,
            'width' => 8,
            'height' => 4,
            'unit' => 'IN',
            'empty_weight' => 1,
            'empty_unit' => 'LB',
        ]],
    ])->assertCreated()
        ->assertExactJson([]);

    expect((new OnboardingService)->fetchOnboardingStatus($seller)->is_shipping)->toBe(1);

    $this->getJson('/api/seller/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Domestic')
        ->assertJsonPath('data.0.shipping_zone_country.0.country.name', 'United States')
        ->assertJsonPath('data.0.shipping_zone_rates.0.price', 12)
        ->assertJsonPath('data.0.shipping_zone_rates.0.condition_type', 'PRICE')
        ->assertJsonPath('data.0.shipping_zone_rates.0.shipping_rate.name', 'Ground');

    $this->getJson('/api/seller/shipping/packaging')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Mailer')
        ->assertJsonPath('data.0.type', 'BOX')
        ->assertJsonPath('data.0.unit', 'IN')
        ->assertJsonPath('data.0.empty_unit', 'LB')
        ->assertJsonPath('data.0.is_default', 1);
});

test('address autocomplete returns the google payload', function () {
    shippingSellerWithStore();

    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'predictions' => [
                ['description' => '123 Main St'],
            ],
        ]),
    ]);

    $this->postJson('/api/seller/shipping/address/autocomplete', [
        'keyword' => '123 Main',
    ])->assertOk()
        ->assertJsonPath('status', 'OK')
        ->assertJsonPath('predictions.0.description', '123 Main St');
});

test('places saves the google address and the easypost id', function () {
    [, $store] = shippingSellerWithStore();

    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'result' => [
                'formatted_address' => '123 Main St, New York, NY 10001, USA',
                'geometry' => ['location' => ['lat' => 40.7, 'lng' => -74.0]],
                'address_components' => [
                    ['long_name' => '123', 'types' => ['street_number']],
                    ['long_name' => 'Main St', 'types' => ['route']],
                    ['long_name' => 'New York', 'types' => ['locality']],
                    ['long_name' => 'NY', 'types' => ['administrative_area_level_1']],
                    ['long_name' => '10001', 'types' => ['postal_code']],
                    ['long_name' => 'United States', 'types' => ['country']],
                ],
            ],
        ]),
    ]);

    $easypostAddress = new Address;
    $easypostAddress->id = 'adr_123';
    $this->mock(EasyPostService::class, function ($mock) use ($easypostAddress) {
        $mock->shouldReceive('createAddress')->once()->andReturn($easypostAddress);
    });

    $this->postJson('/api/seller/shipping/address/places', [
        'place_id' => 'place-1',
    ])->assertOk()
        ->assertJsonPath('data.easypost_id', 'adr_123')
        ->assertJsonPath('data.city', 'New York')
        ->assertJsonPath('data.zipcode', '10001')
        ->assertJsonPath('data.street1', '123 Main St')
        ->assertJsonPath('data.province', 'NY');

    expect($store->address()->first()->easypost_id)->toBe('adr_123');
});
