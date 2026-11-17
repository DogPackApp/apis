<?php

namespace Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RecordingElasticsearchHttpClient implements ClientInterface
{
    /**
     * @var array<int, array{method: string, uri: string, body: string}>
     */
    public array $requests = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'body' => (string) $request->getBody(),
        ];

        $body = $request->getMethod() === 'DELETE'
            ? '{"result":"deleted"}'
            : '{"errors":false,"items":[{"index":{"status":201}}]}';

        return new Response(200, [
            'Content-Type' => 'application/json',
            'X-Elastic-Product' => 'Elasticsearch',
        ], $body);
    }
}
