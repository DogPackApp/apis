<?php

use App\Jobs\Product\ImportProcessCSVJob;
use App\Jobs\Product\ImportValidateProductJob;
use App\Models\Product\Category;
use App\Models\Product\ImportProduct;
use App\Models\Product\Item;
use App\Models\Product\ItemPrice;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function importSeller(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

test('csv import stores the rows and queues validation', function () {
    importSeller();
    Storage::fake('mps3');
    Bus::fake();

    $headers = ['product_id', 'product_title', 'product_type', 'product_media_urls', 'product_description_html', 'variations', 'hide_variant', 'option1_name', 'option1_value', 'option2_name', 'option2_value', 'option3_name', 'option3_value', 'variant_image', 'price', 'quantity', 'continue_selling_when_out_of_stock', 'sku', 'weight', 'unit', 'country_of_origin', 'tags', 'sections', 'product_status'];
    $row = array_fill(0, count($headers), '');
    $row[0] = 'chew-toy';
    $row[1] = 'Chew Toy';
    $row[2] = 'PHYSICAL';
    $row[4] = 'A rubber toy';
    $row[5] = 'No';
    $row[14] = '10';
    $row[15] = '5';
    $row[16] = 'No';
    $row[17] = 'CHEW';
    $row[18] = '100';
    $row[19] = 'g';
    $row[20] = 'US';
    $row[21] = 'Chew';
    $row[22] = 'All';
    $row[23] = 'DRAFT';
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $headers);
    fputcsv($handle, $row);
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $this->post('/api/seller/import/csv', [
        'csv' => UploadedFile::fake()->createWithContent('products.csv', $csv),
    ])->assertOk()
        ->assertJsonStructure(['batchId']);

    expect(ImportProduct::query()->where('title', 'Chew Toy')->exists())->toBeTrue();
    Bus::assertDispatched(ImportValidateProductJob::class);
});

test('csv batch list returns the row errors', function () {
    importSeller();
    $batchId = (string) str()->uuid();
    ImportProduct::query()->create([
        'uuid' => (string) str()->uuid(),
        'batch_id' => $batchId,
        '_id' => 2,
        'product_id' => 'chew-toy',
        'title' => 'Chew Toy',
        'errors' => ['2_B' => 'Title is required'],
    ]);

    $this->getJson('/api/seller/import/csv/'.$batchId)
        ->assertOk()
        ->assertJsonPath('data.0.2_B', 'Title is required');
});

test('csv process queues the import batch', function () {
    importSeller();
    Bus::fake();
    $batchId = (string) str()->uuid();

    $this->postJson('/api/seller/import/csv/process/'.$batchId)
        ->assertOk()
        ->assertJsonPath('message', 'Initiated Importing of Products...');

    Bus::assertDispatched(ImportProcessCSVJob::class);
});

test('bulk list and update edit the selected product', function () {
    [, $store] = importSeller();
    $category = Category::query()->create([
        'name' => 'Toys',
        'slug' => 'toys-bulk',
        'is_active' => true,
    ]);
    $item = Item::factory()->for($store)->create([
        'title' => 'Chew Toy',
        'category_id' => $category->id,
        'status' => Item::STATUS[Item::STATUS_DRAFT],
    ]);
    ItemPrice::query()->create([
        'item_id' => $item->id,
        'domestic_price' => 1000,
        'is_global_pricing' => false,
        'stock_quantity' => 1,
        'weight' => 10,
        'allow_overselling' => false,
    ]);

    $this->postJson('/api/seller/bulk', [
        'products' => [(string) $item->uuid],
    ])->assertOk()
        ->assertJsonPath('data.0.title', 'Chew Toy')
        ->assertJsonPath('data.0.category.name', 'Toys');

    $this->putJson('/api/seller/bulk', [
        'products' => [[
            'uuid' => (string) $item->uuid,
            'status' => 'DRAFT',
            'title' => 'Renamed Toy',
        ]],
    ])->assertOk()
        ->assertJsonPath('message', 'Products updated successfully');

    expect($item->refresh()->title)->toBe('Renamed Toy');
});
