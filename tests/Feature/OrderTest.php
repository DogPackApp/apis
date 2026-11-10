<?php

use App\Models\Order\Customers;
use App\Models\Order\OrderBilling;
use App\Models\Order\StoreOrder;
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

    $this->getJson('/api/seller/orders')
        ->assertOk()
        ->assertJsonPath('data.0.order_no', '1001')
        ->assertJsonPath('data.0.customer.name', 'Ada Buyer');

    $this->getJson('/api/seller/orders/'.$order->uuid)
        ->assertOk()
        ->assertJsonPath('data.order_no', '1001')
        ->assertJsonPath('data.customer.email', 'ada@example.com');
});
