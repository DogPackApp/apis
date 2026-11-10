<?php

use App\Models\Product\Gallery;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function gallerySeller(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

test('gallery list requires authentication', function () {
    $this->postJson('/api/seller/store/gallery')->assertUnauthorized();
});

test('a seller can list and search their gallery', function () {
    [, $store] = gallerySeller();
    $other = Store::factory()->create();

    $named = new Gallery;
    $named->store_id = $store->id;
    $named->url = 'uploads/products/toy.webp';
    $named->name = 'Chew toy';
    $named->type = Gallery::TYPE_IMAGE;
    $named->save();

    $otherImage = new Gallery;
    $otherImage->store_id = $other->id;
    $otherImage->url = 'uploads/products/other.webp';
    $otherImage->name = 'Chew toy';
    $otherImage->type = Gallery::TYPE_IMAGE;
    $otherImage->save();

    $this->postJson('/api/seller/store/gallery', ['search' => 'Chew'])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', (string) $named->uuid)
        ->assertJsonPath('data.0.url', 'uploads/products/toy.webp')
        ->assertJsonPath('data.0.name', 'Chew toy')
        ->assertJsonPath('data.0.type', Gallery::TYPE_IMAGE);
});

test('presigned product upload creates gallery rows', function () {
    [, $store] = gallerySeller();

    Storage::shouldReceive('disk')
        ->once()
        ->with('mps3')
        ->andReturn(new class
        {
            public function temporaryUploadUrl(string $path, mixed $expiration, array $options = []): array
            {
                return ['url' => 'https://s3.test/'.$path];
            }
        });

    $response = $this->postJson('/api/seller/aws/presigned', [
        'count' => 1,
        'module' => 'product',
        'data' => [
            ['name' => 'Front', 'type' => 'image'],
        ],
    ])->assertOk();

    $gallery = Gallery::query()->where('store_id', $store->id)->first();
    $prefix = config('filesystems.disks.mps3.path');

    expect($gallery->name)->toBe('Front')
        ->and($gallery->type)->toBe('image')
        ->and($gallery->getRawOriginal('url'))->toStartWith($prefix.'uploads/products/'.$store->uuid.'/');

    $response->assertJsonPath('urls.0', 'https://s3.test/'.ltrim(str_replace($prefix, '', $gallery->getRawOriginal('url')), '/'));
});

test('presigned store upload saves the profile image path', function () {
    [, $store] = gallerySeller();

    Storage::shouldReceive('disk')
        ->once()
        ->with('mps3')
        ->andReturn(new class
        {
            public function temporaryUploadUrl(string $path, mixed $expiration, array $options = []): array
            {
                return ['url' => 'https://s3.test/'.$path];
            }
        });

    $this->postJson('/api/seller/aws/presigned', [
        'count' => 1,
        'module' => 'store',
        'type' => 'profile',
    ])->assertOk()
        ->assertJsonCount(1, 'urls');

    $stored = Store::query()->find($store->id)->getRawOriginal('image');

    expect($stored)->toStartWith(config('filesystems.disks.mps3.path').'uploads/store/'.$store->uuid.'/');
});
