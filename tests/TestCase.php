<?php

namespace Tests;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\RecordingElasticsearchHttpClient;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $http = new RecordingElasticsearchHttpClient;
        $this->app->instance(RecordingElasticsearchHttpClient::class, $http);
        $this->app->instance(Client::class, ClientBuilder::create()
            ->setHttpClient($http)
            ->setHosts(['http://elasticsearch.test'])
            ->build());
    }
}
