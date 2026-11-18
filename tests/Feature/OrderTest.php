<?php

use App\Models\Misc\Country;
use App\Models\Order\Customers;
use App\Models\Order\ExportOrders;
use App\Models\Order\ManualFulfillment;
use App\Models\Order\OrderBilling;
use App\Models\Order\OrderItem;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a seller can list and show an order', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '1001',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_UNFULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    Customers::query()->create([
        'store_order_id' => $order->id,
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'shipping_address' => ['country' => 'CA'],
    ]);
    OrderBilling::query()->forceCreate([
        'store_order_id' => $order->id,
        'subtotal' => 2500,
        'total' => 2500,
    ]);
    $item = Item::factory()->create(['store_id' => $store->id]);
    OrderItem::query()->create([
        'store_order_id' => $order->id,
        'product_id' => $item->id,
        'quantity' => 1,
        'original_price' => 2500,
        'price' => 2500,
        'subtotal' => 2500,
        'item_options' => [],
    ]);

    $this->getJson('/api/marketplace/orders')
        ->assertOk()
        ->assertJsonPath('data.0.order_no', '1001')
        ->assertJsonPath('data.0.customer.name', 'Ada Buyer');

    $this->getJson('/api/marketplace/orders/'.$order->uuid)
        ->assertOk()
        ->assertJsonPath('data.order_no', '1001')
        ->assertJsonPath('data.customer.email', 'ada@example.com')
        ->assertJsonPath('data.items.0.variant_uuid', null);
});

test('a seller can render the buyer order email', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    Country::query()->create([
        'id' => 38,
        'short_name' => 'CA',
        'name' => 'Canada',
        'currency_code' => 'CAD',
        'currency_name' => 'Canadian Dollar',
        'currency_symbol' => '$',
        'is_display' => 1,
        'is_display_front' => 1,
        'is_display_mobile' => 1,
        'order' => 1,
    ]);

    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '1003',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_UNFULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    Customers::query()->create([
        'store_order_id' => $order->id,
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'shipping_address' => ['country' => 'CA'],
    ]);
    OrderBilling::query()->forceCreate([
        'store_order_id' => $order->id,
        'subtotal' => 2500,
        'total' => 2500,
        'currency_details' => [
            'subtotal' => 25,
            'delivery' => 0,
            'discount' => 0,
            'total' => 25,
            'tax' => 0,
        ],
    ]);
    $item = Item::factory()->create(['store_id' => $store->id]);
    OrderItem::query()->create([
        'store_order_id' => $order->id,
        'product_id' => $item->id,
        'quantity' => 1,
        'original_price' => 2500,
        'price' => 2500,
        'subtotal' => 2500,
        'item_options' => [],
        'product_snapshot' => ['name' => 'Collar', 'customization' => null],
        'currency_details' => [
            'converted' => [
                'discountedPrice' => 25,
                'applieddiscount' => 0,
                'discount' => 0,
            ],
        ],
    ]);

    $this->getJson('/api/marketplace/orders/timeline/'.$order->uuid.'/email/order.email')
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Order: 1003') && str_contains($html, 'Ada Buyer'));
});

test('a seller can render the manual fulfillment email', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '1004',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_FULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    Customers::query()->create([
        'store_order_id' => $order->id,
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'shipping_address' => ['country' => 'CA'],
    ]);
    $item = Item::factory()->create(['store_id' => $store->id]);
    OrderItem::query()->create([
        'store_order_id' => $order->id,
        'product_id' => $item->id,
        'quantity' => 1,
        'original_price' => 2500,
        'price' => 2500,
        'subtotal' => 2500,
        'item_options' => [],
        'product_snapshot' => ['name' => 'Collar', 'customization' => null],
    ]);

    $this->getJson('/api/marketplace/orders/timeline/'.$order->uuid.'/email/fulfillment.manual.completed')
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Order #1004 is being processed!') && str_contains($html, 'Collar'));
});

test('an A4 packing slip renders the product type', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '1005',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_UNFULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    Customers::query()->create([
        'store_order_id' => $order->id,
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'shipping_address' => ['country' => 'CA'],
    ]);
    $item = Item::factory()->create(['store_id' => $store->id]);
    OrderItem::query()->create([
        'store_order_id' => $order->id,
        'product_id' => $item->id,
        'quantity' => 1,
        'original_price' => 2500,
        'price' => 2500,
        'subtotal' => 2500,
        'item_options' => [],
        'product_snapshot' => ['name' => 'Collar', 'customization' => null],
    ]);

    $html = view('marketplace.pdfs.packing-slip', [
        'order' => $order->load(['customer', 'store.address', 'items.product']),
    ])->render();

    expect($html)->toContain('Physical')->toContain('Collar');
});

test('manual fulfillment items are the products on the order', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '1002',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_FULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    $item = Item::factory()->create(['store_id' => $store->id]);
    OrderItem::query()->create([
        'store_order_id' => $order->id,
        'product_id' => $item->id,
        'quantity' => 1,
        'original_price' => 1000,
        'price' => 1000,
        'subtotal' => 1000,
        'item_options' => [],
    ]);
    $fulfillment = ManualFulfillment::query()->create([
        'store_order_id' => $order->id,
        'carrier' => 'UPS',
    ]);

    expect($fulfillment->items()->pluck('items.id')->all())->toBe([$item->id]);
});

test('an export batch belongs to its store', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $export = ExportOrders::query()->create([
        'batch_id' => '11111111-1111-1111-1111-111111111111',
        'store_id' => $store->id,
        'filters' => [],
    ]);

    expect($export->store->is($store))->toBeTrue();
});
