<?php

use App\Services\Merchant\GoogleMerchantFeedService;
use ReflectionClass;

function cleanFeedDescription(?string $description): string
{
    $reflection = new ReflectionClass(GoogleMerchantFeedService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('cleanFeedDescription');

    return $method->invoke($service, $description);
}

function feedFallbackDescription(string $title, string $brand, string $category): string
{
    $reflection = new ReflectionClass(GoogleMerchantFeedService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('buildFallbackDescription');

    return $method->invoke($service, $title, $brand, $category);
}

test('encoded html is decoded before tags are stripped', function () {
    expect(cleanFeedDescription(
        '&lt;p&gt;&lt;b&gt;Train Your Dog to Walk Calmly — With the Canny Collar&lt;/b&gt;&lt;/p&gt; &lt;p&gt;Gentle control.&lt;/p&gt;'
    ))->toBe('Train Your Dog to Walk Calmly — With the Canny Collar Gentle control.')
        ->and(cleanFeedDescription('&amp;lt;p&amp;gt;Train Your Dog&amp;lt;/p&amp;gt;'))->toBe('Train Your Dog')
        ->and(cleanFeedDescription('full of flavor and&amp;nbsp; are great &amp;amp; healthy'))->toBe('full of flavor and are great & healthy');
});

test('wrapping quotes are trimmed and inch marks stay', function () {
    expect(cleanFeedDescription('"Soft chew toy"'))->toBe('Soft chew toy')
        ->and(cleanFeedDescription("'Soft chew toy'"))->toBe('Soft chew toy')
        ->and(cleanFeedDescription('Fits a 5"'))->toBe('Fits a 5"');
});

test('an empty description falls back to title, brand, and category', function () {
    expect(feedFallbackDescription('Stuffing-less Shark Toy', 'The Family Pet', 'Dogs - Toys'))
        ->toBe('Stuffing-less Shark Toy by The Family Pet. Dogs - Toys.')
        ->and(feedFallbackDescription('Shark Toy', 'The Family Pet', ''))->toBe('Shark Toy by The Family Pet.')
        ->and(feedFallbackDescription('Shark Toy', '', 'Dogs - Toys'))->toBe('Shark Toy. Dogs - Toys.')
        ->and(feedFallbackDescription('', 'The Family Pet', 'Dogs - Toys'))->toBe('')
        ->and(cleanFeedDescription('<p> </p>&nbsp;<br>'))->toBe('');
});
