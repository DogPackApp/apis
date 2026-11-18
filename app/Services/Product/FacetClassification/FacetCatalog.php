<?php

namespace App\Services\Product\FacetClassification;

use Illuminate\Support\Collection;

class FacetCatalog
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $facetsById = [];

    /**
     * @var array<int, array{category_facet_id: int, facet_title: string, value_name: string}>
     */
    private array $valuesById = [];

    /**
     * @var array<int, array<int, array{id: int, name: string}>>
     */
    private array $valuesByFacetId = [];

    public function __construct(private readonly Collection $facets)
    {
        foreach ($facets as $facet) {
            $facetId = (int) $facet['category_facet_id'];
            $this->facetsById[$facetId] = $facet;
            $this->valuesByFacetId[$facetId] = [];

            foreach ($facet['values'] as $value) {
                $valueId = (int) $value['id'];
                $this->valuesById[$valueId] = [
                    'category_facet_id' => $facetId,
                    'facet_title' => $facet['title'],
                    'value_name' => $value['name'],
                ];
                $this->valuesByFacetId[$facetId][$valueId] = [
                    'id' => $valueId,
                    'name' => $value['name'],
                ];
            }
        }
    }

    public function facets(): Collection
    {
        return $this->facets;
    }

    public function getFacet(int $categoryFacetId): ?array
    {
        return $this->facetsById[$categoryFacetId] ?? null;
    }

    public function hasFacet(int $categoryFacetId): bool
    {
        return isset($this->facetsById[$categoryFacetId]);
    }

    public function getValue(int $facetValueId): ?array
    {
        return $this->valuesById[$facetValueId] ?? null;
    }

    /**
     * Accepts a mapping only when the value ID exists in this catalog and
     * belongs to the facet it was paired with, so a value borrowed from a
     * neighbouring facet is dropped rather than stored under the wrong one.
     */
    public function ownedValue(int $categoryFacetId, int $facetValueId): ?array
    {
        $value = $this->valuesById[$facetValueId] ?? null;

        if ($value === null || $value['category_facet_id'] !== $categoryFacetId) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function valuesForFacet(int $categoryFacetId): array
    {
        return $this->valuesByFacetId[$categoryFacetId] ?? [];
    }

    /**
     * Finalize a chunk when adding another facet would exceed either the
     * facet count or the total value count. A single oversized facet is
     * placed in its own chunk rather than being split.
     *
     * @return array<int, Collection>
     */
    public function chunk(
        int $maxFacetsPerChunk = FacetClassificationConfig::MAX_FACETS_PER_CHUNK,
        int $maxValuesPerChunk = FacetClassificationConfig::MAX_VALUES_PER_CHUNK
    ): array {
        $chunks = [];
        $current = [];
        $facetCount = 0;
        $valueCount = 0;

        foreach ($this->facets as $facet) {
            $facetValueCount = count($facet['values']);
            $wouldExceed = $current !== [] && (
                ($facetCount + 1) > $maxFacetsPerChunk
                || ($valueCount + $facetValueCount) > $maxValuesPerChunk
            );

            if ($wouldExceed) {
                $chunks[] = collect($current);
                $current = [];
                $facetCount = 0;
                $valueCount = 0;
            }

            $current[] = $facet;
            $facetCount++;
            $valueCount += $facetValueCount;
        }

        if ($current !== []) {
            $chunks[] = collect($current);
        }

        return $chunks;
    }

    /**
     * Stable fingerprint of the IDs the catalog currently contains, used so
     * cached aliases are not reused against a different set of allowed values.
     */
    public function idFingerprint(): string
    {
        $facetIds = array_keys($this->facetsById);
        sort($facetIds);

        $valueIds = array_keys($this->valuesById);
        sort($valueIds);

        return hash('xxh128', json_encode(
            ['f' => $facetIds, 'v' => $valueIds],
            JSON_THROW_ON_ERROR
        ));
    }

    public function facetValueFingerprint(int $categoryFacetId): string
    {
        $valueIds = array_keys($this->valuesByFacetId[$categoryFacetId] ?? []);
        sort($valueIds);

        return hash('xxh128', json_encode($valueIds, JSON_THROW_ON_ERROR));
    }
}
