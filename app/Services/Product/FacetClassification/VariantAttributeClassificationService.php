<?php

namespace App\Services\Product\FacetClassification;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

class VariantAttributeClassificationService
{
    public function __construct(
        private readonly ConstrainedFacetMatchClient $constrainedMatcher,
        private readonly CacheRepository $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Process every variant attribute option independently and accumulate
     * matches in PHP. Deterministic hits never call the LLM.
     *
     * @return array<int, FacetMapping>
     */
    public function classify(array $variantAttributes, Collection $facets): array
    {
        if ($variantAttributes === [] || $facets->isEmpty()) {
            return [];
        }

        $catalog = new FacetCatalog($facets);
        $resolver = new FacetAliasResolver($catalog);
        $accumulator = new FacetMappingAccumulator;
        $allowedFacets = $facets
            ->map(fn (array $facet) => [
                'category_facet_id' => (int) $facet['category_facet_id'],
                'title' => $facet['title'],
            ])
            ->values()
            ->all();

        foreach ($variantAttributes as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            $attributeName = trim((string) ($attribute['name'] ?? ''));
            $options = array_values(array_filter(
                (array) ($attribute['options'] ?? []),
                fn ($option) => is_string($option) && trim($option) !== ''
            ));

            if ($attributeName === '' || $options === []) {
                continue;
            }

            $facet = $this->resolveFacet(
                $attributeName,
                $catalog,
                $resolver,
                $allowedFacets
            );

            if ($facet === null) {
                continue;
            }

            $this->classifyOptions(
                $attributeName,
                $options,
                $facet,
                $catalog,
                $resolver,
                $accumulator
            );
        }

        return array_values($accumulator->all());
    }

    private function resolveFacet(
        string $attributeName,
        FacetCatalog $catalog,
        FacetAliasResolver $resolver,
        array $allowedFacets
    ): ?array {
        $normalized = FacetTextNormalizer::normalize($attributeName);

        if ($normalized === null) {
            return null;
        }

        $facetId = $resolver->resolveFacetId($normalized);

        if ($facetId !== null) {
            $this->logger->info('[FacetClassification] deterministic variant attribute match', [
                'attribute_name' => $attributeName,
                'category_facet_id' => $facetId,
            ]);

            return $catalog->getFacet($facetId);
        }

        $cacheKey = $this->attributeCacheKey($normalized, $catalog);
        $cachedId = $this->cache->get($cacheKey);

        if (is_int($cachedId) && $catalog->hasFacet($cachedId)) {
            $this->logger->info('[FacetClassification] cached variant attribute match', [
                'attribute_name' => $attributeName,
                'category_facet_id' => $cachedId,
            ]);

            return $catalog->getFacet($cachedId);
        }

        if ($cachedId === false) {
            return null;
        }

        try {
            $facetId = $this->constrainedMatcher->matchAttributeName(
                $attributeName,
                $allowedFacets
            );
        } catch (Throwable $exception) {
            $this->logger->error('[FacetClassification] attribute LLM fallback failed', [
                'attribute_name' => $attributeName,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($facetId === null || ! $catalog->hasFacet($facetId)) {
            if ($facetId !== null) {
                $this->logger->info('[FacetClassification] rejected invalid attribute facet id', [
                    'attribute_name' => $attributeName,
                    'returned_category_facet_id' => $facetId,
                ]);
            }

            $this->cache->put($cacheKey, false, FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS);

            return null;
        }

        $this->cache->put($cacheKey, $facetId, FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS);

        $this->logger->info('[FacetClassification] LLM variant attribute match', [
            'attribute_name' => $attributeName,
            'category_facet_id' => $facetId,
        ]);

        return $catalog->getFacet($facetId);
    }

    /**
     * @param  array<int, string>  $options
     */
    private function classifyOptions(
        string $attributeName,
        array $options,
        array $facet,
        FacetCatalog $catalog,
        FacetAliasResolver $resolver,
        FacetMappingAccumulator $accumulator
    ): void {
        $facetId = (int) $facet['category_facet_id'];
        $unresolved = [];

        foreach ($options as $option) {
            $normalized = FacetTextNormalizer::normalize($option);

            if ($normalized === null) {
                continue;
            }

            $valueId = $resolver->resolveValueId($facetId, $normalized);

            if ($valueId !== null) {
                $this->addMapping(
                    $accumulator,
                    $catalog,
                    $facetId,
                    $valueId,
                    $attributeName,
                    $option,
                    FacetMapping::SOURCE_VARIANT_EXACT,
                    FacetClassificationConfig::CONFIDENCE_EXACT
                );

                continue;
            }

            $cacheKey = $this->optionCacheKey($facetId, $normalized, $catalog);
            $cachedId = $this->cache->get($cacheKey);

            if (is_int($cachedId) && $catalog->ownedValue($facetId, $cachedId) !== null) {
                $this->addMapping(
                    $accumulator,
                    $catalog,
                    $facetId,
                    $cachedId,
                    $attributeName,
                    $option,
                    FacetMapping::SOURCE_VARIANT_LLM,
                    FacetClassificationConfig::CONFIDENCE_LLM
                );
                $this->logger->info('[FacetClassification] cached variant option match', [
                    'attribute_name' => $attributeName,
                    'option' => $option,
                    'category_facet_id' => $facetId,
                    'facet_value_id' => $cachedId,
                ]);

                continue;
            }

            if ($cachedId === false) {
                continue;
            }

            $unresolved[] = $option;
        }

        if ($unresolved === []) {
            return;
        }

        $this->logger->info('[FacetClassification] unresolved options sent to LLM', [
            'attribute_name' => $attributeName,
            'category_facet_id' => $facetId,
            'options' => $unresolved,
        ]);

        $allowedValues = array_values(array_map(
            fn (array $value) => [
                'facet_value_id' => $value['id'],
                'name' => $value['name'],
            ],
            $catalog->valuesForFacet($facetId)
        ));

        try {
            $matches = $this->constrainedMatcher->matchOptions(
                $facetId,
                (string) $facet['title'],
                $allowedValues,
                $unresolved
            );
        } catch (Throwable $exception) {
            $this->logger->error('[FacetClassification] option LLM fallback failed', [
                'attribute_name' => $attributeName,
                'category_facet_id' => $facetId,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $matchedOptions = [];

        foreach ($matches as $match) {
            $option = (string) ($match['option'] ?? '');
            $valueId = $match['facet_value_id'] ?? null;
            $matchedOptions[$option] = true;

            if ($option === '' || $valueId === null) {
                $normalized = FacetTextNormalizer::normalize($option);
                if ($normalized !== null) {
                    $this->cache->put(
                        $this->optionCacheKey($facetId, $normalized, $catalog),
                        false,
                        FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS
                    );
                }

                continue;
            }

            $valueId = (int) $valueId;
            $owned = $catalog->ownedValue($facetId, $valueId);

            if ($owned === null) {
                $this->logger->info('[FacetClassification] rejected invalid option facet_value_id', [
                    'attribute_name' => $attributeName,
                    'option' => $option,
                    'category_facet_id' => $facetId,
                    'returned_facet_value_id' => $valueId,
                ]);

                $normalized = FacetTextNormalizer::normalize($option);
                if ($normalized !== null) {
                    $this->cache->put(
                        $this->optionCacheKey($facetId, $normalized, $catalog),
                        false,
                        FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS
                    );
                }

                continue;
            }

            $normalized = FacetTextNormalizer::normalize($option);
            if ($normalized !== null) {
                $this->cache->put(
                    $this->optionCacheKey($facetId, $normalized, $catalog),
                    $valueId,
                    FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS
                );
            }

            $this->addMapping(
                $accumulator,
                $catalog,
                $facetId,
                $valueId,
                $attributeName,
                $option,
                FacetMapping::SOURCE_VARIANT_LLM,
                FacetClassificationConfig::CONFIDENCE_LLM
            );

            $this->logger->info('[FacetClassification] LLM variant option match', [
                'attribute_name' => $attributeName,
                'option' => $option,
                'category_facet_id' => $facetId,
                'facet_value_id' => $valueId,
                'value_name' => $owned['value_name'],
            ]);
        }

        foreach ($unresolved as $option) {
            if (isset($matchedOptions[$option])) {
                continue;
            }

            $normalized = FacetTextNormalizer::normalize($option);
            if ($normalized !== null) {
                $this->cache->put(
                    $this->optionCacheKey($facetId, $normalized, $catalog),
                    false,
                    FacetClassificationConfig::ALIAS_CACHE_TTL_SECONDS
                );
            }
        }
    }

    private function addMapping(
        FacetMappingAccumulator $accumulator,
        FacetCatalog $catalog,
        int $facetId,
        int $valueId,
        string $attributeName,
        string $option,
        string $source,
        float $confidence
    ): void {
        $owned = $catalog->ownedValue($facetId, $valueId);

        if ($owned === null) {
            return;
        }

        if ($source === FacetMapping::SOURCE_VARIANT_EXACT) {
            $this->logger->info('[FacetClassification] deterministic variant option match', [
                'attribute_name' => $attributeName,
                'option' => $option,
                'category_facet_id' => $facetId,
                'facet_value_id' => $valueId,
                'value_name' => $owned['value_name'],
            ]);
        }

        $evidence = mb_substr(
            'variant '.$attributeName.' option "'.$option.'"',
            0,
            FacetClassificationConfig::MAX_EVIDENCE_LENGTH
        );

        $accumulator->add(new FacetMapping(
            $facetId,
            $valueId,
            $owned['facet_title'],
            $owned['value_name'],
            $confidence,
            $evidence,
            $source
        ));
    }

    private function attributeCacheKey(string $normalized, FacetCatalog $catalog): string
    {
        return FacetClassificationConfig::ALIAS_CACHE_PREFIX
            .'attr:'
            .$normalized
            .':'
            .$catalog->idFingerprint();
    }

    private function optionCacheKey(
        int $facetId,
        string $normalized,
        FacetCatalog $catalog
    ): string {
        return FacetClassificationConfig::ALIAS_CACHE_PREFIX
            .'value:'
            .$facetId
            .':'
            .$normalized
            .':'
            .$catalog->facetValueFingerprint($facetId);
    }
}
