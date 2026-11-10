<?php

namespace App\Services\Product\FacetClassification;

final class FacetMapping
{
    public const SOURCE_VARIANT_EXACT = 'variant_exact';

    public const SOURCE_VARIANT_ALIAS = 'variant_alias';

    public const SOURCE_VARIANT_LLM = 'variant_llm';

    public const SOURCE_TEXT_EXACT = 'text_exact';

    public const SOURCE_SEMANTIC = 'semantic';

    private const PRIORITY = [
        self::SOURCE_VARIANT_EXACT => 40,
        self::SOURCE_VARIANT_ALIAS => 30,
        self::SOURCE_TEXT_EXACT => 25,
        self::SOURCE_VARIANT_LLM => 20,
        self::SOURCE_SEMANTIC => 10,
    ];

    public function __construct(
        public readonly int $categoryFacetId,
        public readonly int $facetValueId,
        public readonly string $facetTitle,
        public readonly string $valueName,
        public readonly float $confidence,
        public readonly string $evidence,
        public readonly string $source,
    ) {}

    public function key(): string
    {
        return $this->categoryFacetId.':'.$this->facetValueId;
    }

    public function priority(): int
    {
        return self::PRIORITY[$this->source] ?? 0;
    }

    public function isStrongerThan(self $other): bool
    {
        if ($this->priority() !== $other->priority()) {
            return $this->priority() > $other->priority();
        }

        return $this->confidence > $other->confidence;
    }

    public function toArray(): array
    {
        return [
            'category_facet_id' => $this->categoryFacetId,
            'facet_value_id' => $this->facetValueId,
            'facet_title' => $this->facetTitle,
            'value_name' => $this->valueName,
            'confidence' => round($this->confidence, 4),
            'evidence' => $this->evidence,
        ];
    }

    public static function fromValidatedArray(array $mapping, string $source): self
    {
        return new self(
            (int) $mapping['category_facet_id'],
            (int) $mapping['facet_value_id'],
            (string) $mapping['facet_title'],
            (string) $mapping['value_name'],
            (float) $mapping['confidence'],
            (string) ($mapping['evidence'] ?? ''),
            $source,
        );
    }
}
