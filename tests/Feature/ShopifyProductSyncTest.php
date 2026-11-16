<?php

use App\Models\Order\Customers;
use App\Models\Order\OrderBilling;
use App\Models\Order\StoreOrder;
use App\Models\Product\Category;
use App\Models\Product\Item;
use App\Models\Product\ItemSizingChart;
use App\Models\Store\Store;
use App\Models\Store\StoreShopifyConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
    Config::set('services.shopify_hmac_token', 'shopify-test-token');
    Http::fake();
    Mail::fake();
});

function shopifyHeaders(Store $store): array
{
    return [
        'X-Shopify-DP-Token' => 'Bearer shopify-test-token',
        'X-Shopify-DP-Store' => $store->uuid,
        'Accept' => 'application/json',
    ];
}

function shopifyProductPayload(string $sourceId = 'gid://shopify/Product/1'): array
{
    return [
        'source_id' => $sourceId,
        'status' => 'ACTIVE',
        'is_bundled_product' => false,
        'about' => [
            'title' => 'Shopify collar',
            'type' => 'PHYSICAL',
            'description' => 'A collar',
            'origin' => 'CA',
            'images' => [
                ['url' => 'https://cdn.shopify.com/collar.jpg', 'media_type' => 'IMAGE'],
            ],
        ],
        'price_inventory' => [
            'domestic_price' => 12,
            'weight' => 1,
            'unit' => 'KG',
            'stock_quantity' => 3,
            'is_global_pricing' => false,
            'global_price' => 12,
        ],
        'item_variations' => [
            'variants' => [
                ['name' => 'Size', 'options' => ['Small']],
            ],
            'combination' => [
                [
                    'shopify_id' => 'gid://shopify/ProductVariant/9',
                    'variants' => [
                        ['type' => 'Size', 'value' => 'Small'],
                    ],
                    'domestic_price' => 12,
                    'quantity' => 3,
                    'is_visible' => true,
                ],
            ],
            'vary_by' => [
                'sku' => false,
                'quantity' => false,
                'price' => false,
                'weight' => false,
            ],
        ],
    ];
}

test('shopify can create an active product', function () {
    $category = new Category;
    $category->id = 278;
    $category->name = 'Wall Art';
    $category->slug = 'wall-art';
    $category->is_active = true;
    $category->save();

    $store = Store::factory()->create();

    $this->withHeaders(shopifyHeaders($store))
        ->postJson('/api/marketplace/shopify/product/create', shopifyProductPayload())
        ->assertCreated();

    $item = Item::query()->where('source_id', 'gid://shopify/Product/1')->first();

    expect($item)->not->toBeNull()
        ->and($item->title)->toBe('Shopify collar')
        ->and($item->store_id)->toBe($store->id)
        ->and($item->status)->toBe(Item::STATUS[Item::STATUS_ACTIVE]);
});

test('shopify stores the sizing chart on the product', function () {
    $category = new Category;
    $category->id = 278;
    $category->name = 'Wall Art';
    $category->slug = 'wall-art';
    $category->is_active = true;
    $category->save();

    $store = Store::factory()->create();
    $payload = shopifyProductPayload('gid://shopify/Product/chart');
    $payload['sizing_chart'] = [
        'source' => 'kiwi',
        'charts' => [
            [
                'id' => 'chart-1',
                'name' => 'Collars',
                'table_html' => '<table></table>',
            ],
        ],
    ];

    $this->withHeaders(shopifyHeaders($store))
        ->postJson('/api/marketplace/shopify/product/create', $payload)
        ->assertCreated();

    $item = Item::query()->where('source_id', 'gid://shopify/Product/chart')->first();
    $chart = ItemSizingChart::query()->where('item_id', $item->id)->first();

    expect($chart)->not->toBeNull()
        ->and($chart->source)->toBe('kiwi')
        ->and($chart->charts[0]['name'])->toBe('Collars');
});

test('shopify rejects a request without the token', function () {
    $store = Store::factory()->create();

    $this->postJson('/api/marketplace/shopify/product/create', shopifyProductPayload(), [
        'X-Shopify-DP-Store' => $store->uuid,
    ])->assertUnauthorized();
});

test('shopify archives a connected product on delete', function () {
    $store = Store::factory()->create();
    $item = Item::factory()->create([
        'store_id' => $store->id,
        'source_id' => 'gid://shopify/Product/2',
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
    ]);
    StoreShopifyConnection::query()->create(['store_id' => $store->id]);

    $this->withHeaders(shopifyHeaders($store))
        ->postJson('/api/marketplace/shopify/product/delete', [
            'source_id' => 'gid://shopify/Product/2',
        ])
        ->assertNoContent();

    expect($item->fresh()->status)->toBe(Item::STATUS[Item::STATUS_ARCHIVED]);
});

test('shopify can fulfill an order by shopify order id', function () {
    $store = Store::factory()->create();
    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '2002',
        'source_id' => 'gid://shopify/Order/55',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'order_status' => StoreOrder::ORDER_STATUS_PENDING,
        'fulfilment_status' => StoreOrder::ORDER_UNFULFILLED,
    ]);
    Customers::query()->create([
        'store_order_id' => $order->id,
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'shipping_address' => ['country' => 'CA'],
    ]);
    OrderBilling::query()->forceCreate([
        'store_order_id' => $order->id,
        'subtotal' => 1200,
        'total' => 1200,
    ]);

    $this->withHeaders(shopifyHeaders($store))
        ->postJson('/api/marketplace/shopify/order/fulfill', [
            'shopify_order_id' => 'gid://shopify/Order/55',
            'tracking' => '1Z999',
            'carrier' => 'UPS',
        ])
        ->assertSuccessful();

    expect($order->fresh()->fulfilment_status)->toBe(StoreOrder::ORDER_FULFILLED);
});
