<?php

use App\Jobs\Product\GenerateGoogleFeedJob;
use App\Services\Merchant\GoogleMerchantFeedService;
use RuntimeException;

uses(Tests\TestCase::class);

test('the google feed job runs once on the elasticsearch queue', function () {
    $job = new GenerateGoogleFeedJob('ca');

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(1200)
        ->and($job->connection)->toBe('elasticsearch')
        ->and($job->queue)->toBe('elasticsearch')
        ->and(config('queue.connections.elasticsearch.retry_after'))->toBeGreaterThan($job->timeout);
});

test('the google feed job rethrows a generation failure', function () {
    $service = Mockery::mock(GoogleMerchantFeedService::class);
    $service->shouldReceive('generateGoogleAndAwin')
        ->once()
        ->andThrow(new RuntimeException('feed failed'));

    expect(fn () => (new GenerateGoogleFeedJob('CA'))->handle($service))
        ->toThrow(RuntimeException::class, 'feed failed');
});
