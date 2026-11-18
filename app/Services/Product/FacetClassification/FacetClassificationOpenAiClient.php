<?php

namespace App\Services\Product\FacetClassification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FacetClassificationOpenAiClient
{
    private string $apiKey;

    /**
     * One entry per successful OpenAI call in the current classification.
     *
     * @var array<int, array{prompts: array<int, mixed>, usage: array<string, int>}>
     */
    private array $collectedCalls = [];

    public function __construct()
    {
        $this->apiKey = (string) config('services.open_ai.facet_classification_key');
    }

    /**
     * @return array<string, int>|null
     */
    public function drainUsage(): ?array
    {
        return $this->drainClassificationLog()['usage'];
    }

    /**
     * Prompts and token usage for every OpenAI call since the last drain.
     *
     * `prompts` is an array of arrays: one inner list per call, containing
     * only the dynamic payloads (catalog, product, attribute, options).
     * Instruction text, JSON schemas, and I/O format structures are omitted.
     *
     * @return array{prompts: array<int, array<int, mixed>>, usage: array<string, int>|null}
     */
    public function drainClassificationLog(): array
    {
        $prompts = [];
        $usage = [
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
        ];
        $hadCalls = $this->collectedCalls !== [];

        foreach ($this->collectedCalls as $call) {
            $prompts[] = $call['prompts'];

            foreach (array_keys($usage) as $token) {
                $usage[$token] += (int) ($call['usage'][$token] ?? 0);
            }
        }

        $this->collectedCalls = [];

        return [
            'prompts' => $prompts,
            'usage' => $hadCalls ? $usage : null,
        ];
    }

    /**
     * @param  array<int, string>  $systemTexts
     * @param  array<int, mixed>  $loggedPrompts  Dynamic payloads for this call.
     *                                            When empty, they are inferred from $systemTexts and $userText.
     * @return array{data: array, usage: array<string, int>}
     */
    public function complete(
        array $systemTexts,
        string $userText,
        array $schema,
        string $schemaName,
        array $logContext = [],
        int $maxOutputTokens = FacetClassificationConfig::MAX_OUTPUT_TOKENS,
        array $loggedPrompts = [],
    ): array {
        $payload = [
            'model' => FacetClassificationConfig::MODEL,
            'store' => false,
            'temperature' => 0,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => array_map(
                        fn (string $text) => [
                            'type' => 'input_text',
                            'text' => $text,
                        ],
                        $systemTexts
                    ),
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $userText,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(45)
                ->retry(3, 1000, throw: false)
                ->post('https://api.openai.com/v1/responses', $payload);

            if (! $response->successful()) {
                Log::error('OpenAI facet classification failed', array_merge($logContext, [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'schema' => $schemaName,
                ]));

                throw new RuntimeException(
                    "OpenAI request failed with status {$response->status()}."
                );
            }

            $body = $response->json();

            if (data_get($body, 'status') === 'incomplete') {
                throw new RuntimeException(
                    'OpenAI classification was incomplete: '
                    .(data_get($body, 'incomplete_details.reason') ?? 'unknown reason')
                    .'.'
                );
            }

            $outputText = $this->extractOutputText($body);

            if ($outputText === null) {
                throw new RuntimeException('OpenAI returned no classification output.');
            }

            $decoded = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new RuntimeException(
                    'OpenAI returned an unexpected classification payload.'
                );
            }

            $usage = [
                'input_tokens' => (int) data_get($body, 'usage.input_tokens'),
                'cached_input_tokens' => (int) data_get(
                    $body,
                    'usage.input_tokens_details.cached_tokens'
                ),
                'output_tokens' => (int) data_get($body, 'usage.output_tokens'),
            ];

            $this->collectedCalls[] = [
                'prompts' => $loggedPrompts !== []
                    ? array_values($loggedPrompts)
                    : $this->dynamicPromptElements($systemTexts, $userText),
                'usage' => $usage,
            ];

            return [
                'data' => $decoded,
                'usage' => $usage,
            ];
        } catch (Throwable $exception) {
            Log::error('Item facet classification exception', array_merge($logContext, [
                'schema' => $schemaName,
                'exception' => $exception->getMessage(),
            ]));

            throw $exception;
        }
    }

    /**
     * Keeps only the per-call payloads that change between items and chunks.
     * Static instruction prompts and JSON Schema / I/O format blocks are dropped.
     *
     * @param  array<int, string>  $systemTexts
     * @return array<int, mixed>
     */
    private function dynamicPromptElements(array $systemTexts, string $userText): array
    {
        $elements = [];

        foreach ($systemTexts as $text) {
            $element = $this->dynamicPromptElement($text);

            if ($element !== null) {
                $elements[] = $element;
            }
        }

        $userElement = $this->dynamicPromptElement($userText);
        $elements[] = $userElement ?? $userText;

        return $elements;
    }

    private function dynamicPromptElement(string $text): mixed
    {
        $trimmed = trim($text);
        $objectPos = strpos($trimmed, '{');
        $arrayPos = strpos($trimmed, '[');
        $starts = array_filter(
            [$objectPos, $arrayPos],
            fn ($position) => $position !== false
        );

        if ($starts === []) {
            return null;
        }

        $decoded = json_decode(substr($trimmed, min($starts)), true);

        if (! is_array($decoded) || $this->isJsonSchema($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function isJsonSchema(array $value): bool
    {
        return isset($value['type'])
            || isset($value['additionalProperties'])
            || isset($value['properties'])
            || isset($value['$schema']);
    }

    private function extractOutputText(mixed $body): ?string
    {
        foreach ((array) data_get($body, 'output', []) as $outputItem) {
            foreach ((array) data_get($outputItem, 'content', []) as $content) {
                if (data_get($content, 'type') !== 'output_text') {
                    continue;
                }

                $text = data_get($content, 'text');

                if (is_string($text) && $text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }
}
