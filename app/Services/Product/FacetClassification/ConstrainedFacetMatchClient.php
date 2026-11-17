<?php

namespace App\Services\Product\FacetClassification;

use Illuminate\Support\Facades\Log;

class ConstrainedFacetMatchClient
{
    public function __construct(
        private readonly FacetClassificationOpenAiClient $openAiClient,
    ) {}

    public function matchAttributeName(string $attributeName, array $allowedFacets): ?int
    {
        if ($attributeName === '' || $allowedFacets === []) {
            return null;
        }

        $allowedIds = array_map(
            fn (array $facet) => (int) $facet['category_facet_id'],
            $allowedFacets
        );

        $payload = [
            'attribute_name' => $attributeName,
            'allowed_facets' => array_values(array_map(
                fn (array $facet) => [
                    'category_facet_id' => (int) $facet['category_facet_id'],
                    'title' => $facet['title'],
                ],
                $allowedFacets
            )),
        ];

        $result = $this->openAiClient->complete(
            [$this->attributeNamePrompt()],
            'Attribute: '.json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            $this->attributeNameSchema(),
            'facet_attribute_name_match',
            ['attribute_name' => $attributeName],
            FacetClassificationConfig::MAX_CONSTRAINED_OUTPUT_TOKENS,
            [$payload],
        );

        $facetId = $result['data']['category_facet_id'] ?? null;

        if ($facetId === null || $facetId === '') {
            return null;
        }

        $facetId = (int) $facetId;

        if (! in_array($facetId, $allowedIds, true)) {
            Log::info('[FacetClassification] rejected invalid attribute facet id', [
                'attribute_name' => $attributeName,
                'returned_category_facet_id' => $facetId,
            ]);

            return null;
        }

        return $facetId;
    }

    public function matchOptions(
        int $categoryFacetId,
        string $facetTitle,
        array $allowedValues,
        array $options
    ): array {
        if ($options === [] || $allowedValues === []) {
            return [];
        }

        $allowedIds = array_map(
            fn (array $value) => (int) $value['facet_value_id'],
            $allowedValues
        );

        $payload = [
            'facet' => [
                'category_facet_id' => $categoryFacetId,
                'title' => $facetTitle,
            ],
            'allowed_values' => array_values($allowedValues),
            'options' => array_values($options),
        ];

        $result = $this->openAiClient->complete(
            [$this->optionMatchPrompt()],
            'Option matching request: '.json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            $this->optionMatchSchema(),
            'facet_option_value_match',
            [
                'category_facet_id' => $categoryFacetId,
                'option_count' => count($options),
            ],
            FacetClassificationConfig::MAX_CONSTRAINED_OUTPUT_TOKENS,
            [$payload],
        );

        $matches = [];

        foreach (($result['data']['matches'] ?? []) as $match) {
            if (! is_array($match)) {
                continue;
            }

            $option = (string) ($match['option'] ?? '');
            $valueId = $match['facet_value_id'] ?? null;

            if ($option === '') {
                continue;
            }

            if ($valueId === null || $valueId === '') {
                $matches[] = [
                    'option' => $option,
                    'facet_value_id' => null,
                ];

                continue;
            }

            $valueId = (int) $valueId;

            if (! in_array($valueId, $allowedIds, true)) {
                Log::info('[FacetClassification] rejected invalid option facet_value_id', [
                    'category_facet_id' => $categoryFacetId,
                    'option' => $option,
                    'returned_facet_value_id' => $valueId,
                ]);
                $matches[] = [
                    'option' => $option,
                    'facet_value_id' => null,
                ];

                continue;
            }

            $matches[] = [
                'option' => $option,
                'facet_value_id' => $valueId,
            ];
        }

        return $matches;
    }

    private function attributeNamePrompt(): string
    {
        return <<<'PROMPT'
                Determine whether the variant attribute name unambiguously corresponds
                to exactly one allowed facet.

                The text may be in another language.

                Return only an existing category_facet_id.

                If no facet matches unambiguously, return null.

                Do not infer from product category or plausibility.
                PROMPT;
    }

    private function optionMatchPrompt(): string
    {
        return <<<'PROMPT'
                Map each input option independently to an allowed value.

                The option may be written in another language.

                Return a mapping only when the option unambiguously means the same thing
                as one of the allowed values.

                Do not use associations, product norms, or approximate concepts.

                Process EVERY option independently.

                Do not stop after finding the first match.

                Do not select a representative subset.

                Return null/unmatched for unsupported options.

                Only return facet_value_id values present in allowed_values.
                PROMPT;
    }

    private function attributeNameSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'category_facet_id' => [
                    'anyOf' => [
                        ['type' => 'integer'],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'required' => ['category_facet_id'],
        ];
    }

    private function optionMatchSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'matches' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'option' => [
                                'type' => 'string',
                            ],
                            'facet_value_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                            ],
                        ],
                        'required' => ['option', 'facet_value_id'],
                    ],
                ],
            ],
            'required' => ['matches'],
        ];
    }
}
