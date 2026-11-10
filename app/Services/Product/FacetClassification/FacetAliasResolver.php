<?php

namespace App\Services\Product\FacetClassification;

class FacetAliasResolver
{
    /**
     * @var array<string, array<int, int>>
     */
    private array $titleIndex = [];

    /**
     * @var array<int, array<string, array<int, int>>>
     */
    private array $valueIndex = [];

    /**
     * Normalized value label → every (facet, value) pair that shares that label.
     * Used for free-text scans where the facet is not known up front.
     *
     * @var array<string, array<int, array{category_facet_id: int, facet_value_id: int, value_name: string}>>
     */
    private array $globalValueIndex = [];

    public function __construct(
        FacetCatalog $catalog,
    ) {
        foreach ($catalog->facets() as $facet) {
            $facetId = (int) $facet['category_facet_id'];
            $normalizedTitle = FacetTextNormalizer::normalize($facet['title'] ?? '');

            if ($normalizedTitle !== null) {
                $this->titleIndex[$normalizedTitle][$facetId] = $facetId;
            }

            foreach ($facet['values'] as $value) {
                $normalizedName = FacetTextNormalizer::normalize($value['name'] ?? '');

                if ($normalizedName === null) {
                    continue;
                }

                $valueId = (int) $value['id'];
                $this->valueIndex[$facetId][$normalizedName][$valueId] = $valueId;
                $this->globalValueIndex[$normalizedName][$valueId] = [
                    'category_facet_id' => $facetId,
                    'facet_value_id' => $valueId,
                    'value_name' => (string) $value['name'],
                ];
            }
        }
    }

    public function resolveFacetId(string $normalizedLabel): ?int
    {
        return $this->uniqueId($this->titleIndex[$normalizedLabel] ?? []);
    }

    public function resolveValueId(int $categoryFacetId, string $normalizedLabel): ?int
    {
        return $this->uniqueId(
            $this->valueIndex[$categoryFacetId][$normalizedLabel] ?? []
        );
    }

    /**
     * Resolves a label only when it identifies exactly one value across the
     * whole catalog. Labels shared by several facets (e.g. "Chicken" under
     * Protein and Flavor, or "Small" under Dog Size and Package Size) return
     * null so free-text matching cannot guess the wrong facet.
     *
     * @return array{category_facet_id: int, facet_value_id: int, value_name: string}|null
     */
    public function resolveUniqueValue(string $normalizedLabel): ?array
    {
        $candidates = array_values($this->globalValueIndex[$normalizedLabel] ?? []);

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Every normalized label that uniquely identifies one catalog value,
     * longest first so "small breed" wins over "small" in a text scan.
     *
     * @return array<int, array{normalized: string, category_facet_id: int, facet_value_id: int, value_name: string}>
     */
    public function uniqueNormalizedValues(): array
    {
        $values = [];

        foreach ($this->globalValueIndex as $normalized => $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }

            $candidate = array_values($candidates)[0];
            $values[] = [
                'normalized' => $normalized,
                'category_facet_id' => $candidate['category_facet_id'],
                'facet_value_id' => $candidate['facet_value_id'],
                'value_name' => $candidate['value_name'],
            ];
        }

        usort(
            $values,
            fn (array $left, array $right) => mb_strlen($right['normalized']) <=> mb_strlen($left['normalized'])
        );

        return $values;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function uniqueId(array $ids): ?int
    {
        $ids = array_values(array_unique($ids));

        return count($ids) === 1 ? $ids[0] : null;
    }
}
