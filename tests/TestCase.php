<?php

namespace Tests;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingElasticsearchHttpClient;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->shareSqliteReaderConnection();

        $http = new RecordingElasticsearchHttpClient;
        $this->app->instance(RecordingElasticsearchHttpClient::class, $http);
        $this->app->instance(Client::class, ClientBuilder::create()
            ->setHttpClient($http)
            ->setHosts(['http://elasticsearch.test'])
            ->build());

        $this->registerSqliteDateFormat();
    }

    /**
     * Seller reads use the mysql_reader connection. Point that name at the
     * same SQLite database the test already writes to.
     */
    private function shareSqliteReaderConnection(): void
    {
        $default = DB::connection();

        if ($default->getDriverName() !== 'sqlite') {
            return;
        }

        config([
            'database.connections.mysql_reader' => config('database.connections.sqlite'),
        ]);

        DB::purge('mysql_reader');

        $reader = DB::connection('mysql_reader');
        $reader->setPdo($default->getPdo());
        $reader->setReadPdo($default->getReadPdo());
    }

    /**
     * The seller total-sales query uses MySQL DATE_FORMAT. Teach that function
     * to the SQLite connection so the test runs the same SQL.
     */
    private function registerSqliteDateFormat(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->getPdo()->sqliteCreateFunction(
            'DATE_FORMAT',
            function (?string $date, ?string $format): ?string {
                if ($date === null || $date === '') {
                    return null;
                }

                $timestamp = strtotime($date);

                if ($timestamp === false) {
                    return null;
                }

                $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][(int) date('n', $timestamp) - 1];

                return strtr($format ?? '', [
                    '%Y' => date('Y', $timestamp),
                    '%m' => date('m', $timestamp),
                    '%b' => $month,
                ]);
            },
            2,
        );
    }
}
