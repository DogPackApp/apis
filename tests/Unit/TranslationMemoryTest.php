<?php

use App\Models\Product\TranslationMemory;
use App\Services\Product\GoogleTranslateService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithSellerAuth;
use Tests\TestCase;

uses(TestCase::class, InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a remembered translation is reused without calling google', function () {
    Http::fake();

    TranslationMemory::insert([
        'source_hash' => md5('Leash'),
        'from_language' => 'en',
        'to_language' => 'fr',
        'translated_text' => 'Laisse',
        'created_at' => now(),
    ]);

    $service = new GoogleTranslateService;

    expect($service->translateBatch(['Leash'], 'fr', 'en'))->toBe(['Laisse'])
        ->and($service->lastCallFailed())->toBeFalse();

    Http::assertNothingSent();
});

test('a failed google call marks the batch so the original text is not stored as a translation', function () {
    config(['services.google_translate.api_key' => 'test-key']);

    Http::fake([
        'translation.googleapis.com/*' => Http::response(['error' => ['message' => 'denied']], 403),
    ]);

    $service = new GoogleTranslateService;

    expect($service->translateBatch(['Leash'], 'fr', 'en'))->toBe(['Leash'])
        ->and($service->lastCallFailed())->toBeTrue();

    expect(TranslationMemory::query()->count())->toBe(0);
});

test('a successful google call is stored in translation memory', function () {
    config(['services.google_translate.api_key' => 'test-key']);

    Http::fake([
        'translation.googleapis.com/*' => Http::response([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Laisse'],
                ],
            ],
        ]),
    ]);

    $service = new GoogleTranslateService;
    $service->translateBatch(['Leash'], 'fr', 'en');

    expect(TranslationMemory::query()->where('to_language', 'fr')->value('translated_text'))->toBe('Laisse')
        ->and($service->lastCallFailed())->toBeFalse();
});
