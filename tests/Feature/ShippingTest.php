<?php

use App\Models\Misc\CountriesNew;
use App\Models\Misc\Country;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Packaging;
use App\Models\Store\ShippingRate;
use App\Models\Store\ShippingZone;
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

    $shippingCountry = new CountriesNew;
    $shippingCountry->id = 231;
    $shippingCountry->short_name = 'US';
    $shippingCountry->name = 'United States';
    $shippingCountry->save();

    return $country;
}

test('the shipping zone list stays cached until shipping is saved', function () {
    [, $store] = shippingSellerWithStore();

    $zone = new ShippingZone;
    $zone->store_id = $store->id;
    $zone->name = 'Domestic';
    $zone->save();

    $this->getJson('/api/marketplace/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Domestic');

    ShippingZone::query()->whereKey($zone->id)->update(['name' => 'International']);

    $this->getJson('/api/marketplace/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Domestic');

    ShippingZone::forgetListCache($store->id);

    $this->getJson('/api/marketplace/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'International');
});

test('a store resolves its default packaging', function () {
    [, $store] = shippingSellerWithStore();

    $other = new Packaging;
    $other->store_id = $store->id;
    $other->name = 'Spare box';
    $other->type = Packaging::PACKING_TYPE[Packaging::PACKING_TYPE_BOX];
    $other->length = 4;
    $other->width = 4;
    $other->height = 4;
    $other->unit = Packaging::PACKING_UNIT[Packaging::PACKING_UNIT_IN];
    $other->empty_weight = 1;
    $other->empty_unit = Packaging::PACKING_WEIGHT[Packaging::PACKING_WEIGHT_LB];
    $other->is_default = false;
    $other->save();

    $default = new Packaging;
    $default->store_id = $store->id;
    $default->name = 'Mailer';
    $default->type = Packaging::PACKING_TYPE[Packaging::PACKING_TYPE_BOX];
    $default->length = 10;
    $default->width = 8;
    $default->height = 4;
    $default->unit = Packaging::PACKING_UNIT[Packaging::PACKING_UNIT_IN];
    $default->empty_weight = 1;
    $default->empty_unit = Packaging::PACKING_WEIGHT[Packaging::PACKING_WEIGHT_LB];
    $default->is_default = true;
    $default->save();

    $loaded = Store::query()->with('defaultPackaging')->find($store->id);

    expect($loaded->defaultPackaging)->not->toBeNull()
        ->and($loaded->defaultPackaging->is($default))->toBeTrue()
        ->and($loaded->defaultPackaging->name)->toBe('Mailer');
});

test('shipping routes require authentication', function () {
    $this->getJson('/api/marketplace/shipping/rates')->assertUnauthorized();
    $this->postJson('/api/marketplace/shipping', [])->assertUnauthorized();
    $this->getJson('/api/marketplace/shipping/shipping')->assertUnauthorized();
    $this->getJson('/api/marketplace/shipping/packaging')->assertUnauthorized();
});

test('seller sees global rates and can create a custom method', function () {
    [, $store] = shippingSellerWithStore();

    $global = new ShippingRate;
    $global->name = 'Ground';
    $global->description = 'Carrier ground';
    $global->save();

    $created = $this->postJson('/api/marketplace/shipping/custom', [
        'name' => 'Local drop off',
        'description' => 'Hand delivery',
    ])->assertCreated()
        ->assertJsonPath('name', 'Local drop off')
        ->assertJsonPath('description', 'Hand delivery')
        ->json('uuid');

    $this->putJson('/api/marketplace/shipping/custom/'.$created, [
        'name' => 'Local pickup',
        'description' => 'Counter',
    ])->assertOk()
        ->assertJsonPath('uuid', $created)
        ->assertJsonPath('name', 'Local pickup');

    $this->getJson('/api/marketplace/shipping/rates')
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

    $this->postJson('/api/marketplace/shipping', [
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

    $this->getJson('/api/marketplace/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Domestic')
        ->assertJsonPath('data.0.shipping_zone_country.0.country.name', 'United States')
        ->assertJsonPath('data.0.shipping_zone_rates.0.price', 12)
        ->assertJsonPath('data.0.shipping_zone_rates.0.condition_type', 'PRICE')
        ->assertJsonPath('data.0.shipping_zone_rates.0.shipping_rate.name', 'Ground');

    $this->getJson('/api/marketplace/shipping/packaging')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Mailer')
        ->assertJsonPath('data.0.type', 'BOX')
        ->assertJsonPath('data.0.unit', 'IN')
        ->assertJsonPath('data.0.empty_unit', 'LB')
        ->assertJsonPath('data.0.is_default', 1);
});

test('a product rate is saved against the products in that store', function () {
    [$seller, $store] = shippingSellerWithStore();
    (new OnboardingService)->initiateOnboarding($seller);
    $country = shippingCountry();

    $item = Item::factory()->create([
        'store_id' => $store->id,
        'title' => 'Leash',
        'media_url' => 'uploads/products/leash.webp',
    ]);

    $rate = new ShippingRate;
    $rate->name = 'Ground';
    $rate->description = 'Carrier ground';
    $rate->save();

    $this->postJson('/api/marketplace/shipping', [
        'shipping' => [[
            'name' => 'Domestic',
            'country' => [$country->id],
            'shipping_zone_rates' => [[
                'shipping_type' => 1,
                'shipping_rates' => ['value' => (string) $rate->uuid],
                'price' => 8,
                'condition_type' => 'ITEM',
                'item_uuids' => [$item->uuid],
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
    ])->assertCreated();

    $this->getJson('/api/marketplace/shipping/shipping')
        ->assertOk()
        ->assertJsonPath('data.0.shipping_zone_rates.0.condition_type', 'ITEM')
        ->assertJsonPath('data.0.shipping_zone_rates.0.items.0.uuid', $item->uuid)
        ->assertJsonPath('data.0.shipping_zone_rates.0.items.0.title', 'Leash')
        ->assertJsonPath('data.0.shipping_zone_rates.0.items.0.media_url', 'uploads/products/leash.webp');
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

    $this->postJson('/api/marketplace/shipping/address/autocomplete', [
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

    $this->postJson('/api/marketplace/shipping/address/places', [
        'place_id' => 'place-1',
    ])->assertOk()
        ->assertJsonPath('data.easypost_id', 'adr_123')
        ->assertJsonPath('data.city', 'New York')
        ->assertJsonPath('data.zipcode', '10001')
        ->assertJsonPath('data.street1', '123 Main St')
        ->assertJsonPath('data.province', 'NY');

    expect($store->address()->first()->easypost_id)->toBe('adr_123');
});
