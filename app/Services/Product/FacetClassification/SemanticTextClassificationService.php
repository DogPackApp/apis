<?php

namespace App\Services\Product\FacetClassification;

use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Deterministic free-text matcher for title, description, and origin.
 *
 * Mirrors the variant exact path: normalize catalog values, require a unique
 * cross-facet hit, then scan product prose with word boundaries. Ambiguous
 * labels (the same text under two facets) are skipped so OpenAI can resolve
 * them with context.
 */
class SemanticTextClassificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, FacetMapping>
     */
    public function classify(array $product, Collection $facets): array
    {
        $fields = $this->extractFields($product);

        if ($fields === [] || $facets->isEmpty()) {
            return [];
        }

        $catalog = new FacetCatalog($facets);
        $resolver = new FacetAliasResolver($catalog);
        $accumulator = new FacetMappingAccumulator;
        $candidates = array_values(array_filter(
            $resolver->uniqueNormalizedValues(),
            fn (array $candidate) => mb_strlen($candidate['normalized'])
                >= FacetClassificationConfig::MIN_TEXT_MATCH_LENGTH
        ));

        if ($candidates === []) {
            return [];
        }

        foreach ($fields as $field => $text) {
            $this->classifyField(
                $field,
                $text,
                $candidates,
                $catalog,
                $accumulator
            );
        }

        return array_values($accumulator->all());
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, string>
     */
    private function extractFields(array $product): array
    {
        $fields = [];

        foreach (['title', 'description', 'origin'] as $field) {
            $value = $product[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param  array<int, array{normalized: string, category_facet_id: int, facet_value_id: int, value_name: string}>  $candidates
     */
    private function classifyField(
        string $field,
        string $text,
        array $candidates,
        FacetCatalog $catalog,
        FacetMappingAccumulator $accumulator
    ): void {
        $haystack = FacetTextNormalizer::normalize($text);

        if ($haystack === null) {
            return;
        }

        $occupied = [];

        foreach ($candidates as $candidate) {
            $occurrences = $this->findOccurrences(
                $haystack,
                $candidate['normalized']
            );

            if ($occurrences === []) {
                continue;
            }

            $matched = false;

            foreach ($occurrences as [$start, $end]) {
                if ($this->overlaps($occupied, $start, $end)) {
                    continue;
                }

                $occupied[] = [$start, $end];
                $matched = true;
            }

            if (! $matched) {
                continue;
            }

            $owned = $catalog->ownedValue(
                $candidate['category_facet_id'],
                $candidate['facet_value_id']
            );

            if ($owned === null) {
                continue;
            }

            $evidence = mb_substr(
                $field.' "'.$candidate['value_name'].'"',
                0,
                FacetClassificationConfig::MAX_EVIDENCE_LENGTH
            );

            $this->logger->info('[FacetClassification] deterministic text match', [
                'field' => $field,
                'matched' => $candidate['normalized'],
                'category_facet_id' => $candidate['category_facet_id'],
                'facet_value_id' => $candidate['facet_value_id'],
                'value_name' => $owned['value_name'],
            ]);

            $accumulator->add(new FacetMapping(
                $candidate['category_facet_id'],
                $candidate['facet_value_id'],
                $owned['facet_title'],
                $owned['value_name'],
                FacetClassificationConfig::CONFIDENCE_TEXT_EXACT,
                $evidence,
                FacetMapping::SOURCE_TEXT_EXACT
            ));
        }
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    private function findOccurrences(string $haystack, string $needle): array
    {
        if ($needle === '' || ! str_contains($haystack, $needle)) {
            return [];
        }

        $pattern = '/(?<![\p{L}\p{N}-])'
            .preg_quote($needle, '/')
            .'(?![\p{L}\p{N}-])/u';

        $matchCount = preg_match_all(
            $pattern,
            $haystack,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if ($matchCount === false || $matchCount < 1) {
            return [];
        }

        $occurrences = [];

        foreach ($matches[0] as $match) {
            $byteOffset = (int) $match[1];
            $matched = (string) $match[0];
            $start = mb_strlen(substr($haystack, 0, $byteOffset));
            $end = $start + mb_strlen($matched);
            $occurrences[] = [$start, $end];
        }

        return $occurrences;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $occupied
     */
    private function overlaps(array $occupied, int $start, int $end): bool
    {
        foreach ($occupied as [$occupiedStart, $occupiedEnd]) {
            if ($start < $occupiedEnd && $end > $occupiedStart) {
                return true;
            }
        }

        return false;
    }
}
