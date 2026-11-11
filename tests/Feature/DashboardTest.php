<?php

use App\Models\Order\Customers;
use App\Models\Order\OrderBilling;
use App\Models\Order\OrderItem;
use App\Models\Order\Refund;
use App\Models\Order\StoreOrder;
use App\Models\Review\ItemReview;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('the seller dashboard reports purchases, sales, reviews, and unfulfilled orders', function () {
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
        'total' => 3000,
    ]);
    OrderItem::query()->forceCreate([
        'store_order_id' => $order->id,
        'product_id' => 1,
        'quantity' => 2,
        'original_price' => 1500,
        'price' => 1500,
        'subtotal' => 3000,
    ]);
    Refund::query()->create([
        'store_order_id' => $order->id,
        'refund_id' => 're_test',
        'amount' => 500,
    ]);
    ItemReview::query()->create([
        'store_id' => $store->id,
        'item_id' => 1,
        'username' => 'Ada',
        'rating' => 4,
        'is_visible' => true,
    ]);

    $this->getJson('/api/seller/dashboard/purchases')
        ->assertOk()
        ->assertJsonPath('orders', 1);

    $this->getJson('/api/seller/dashboard/sales')
        ->assertOk()
        ->assertJsonPath('sales', 25);

    $this->getJson('/api/seller/dashboard/reviews')
        ->assertOk()
        ->assertJsonPath('0.totalReviews', 1)
        ->assertJsonPath('0.totalAvg', 4);

    $this->getJson('/api/seller/dashboard/unfulfilled')
        ->assertOk()
        ->assertJsonPath('data.0.customer', 'Ada Buyer')
        ->assertJsonPath('data.0.order_no', '1001')
        ->assertJsonPath('data.0.itemsCount', 2)
        ->assertJsonPath('data.0.total', 30);
});

test('total sales sums fulfilled orders from the last six months', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '2002',
        'fulfilment_status' => StoreOrder::ORDER_FULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    OrderBilling::query()->forceCreate([
        'store_order_id' => $order->id,
        'subtotal' => 2500,
        'total' => 2500,
    ]);

    $this->getJson('/api/seller/analytics/totalSales')
        ->assertOk()
        ->assertJsonPath('total_sales', 25)
        ->assertJsonPath('sales.0.month', now()->format('M'))
        ->assertJsonPath('sales.0.sales', 25);
});
