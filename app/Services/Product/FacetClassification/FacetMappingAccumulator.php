<?php

namespace App\Services\Product\FacetClassification;

class FacetMappingAccumulator
{
    /**
     * @var array<string, FacetMapping>
     */
    private array $mappings = [];

    public function add(FacetMapping $mapping): void
    {
        $key = $mapping->key();
        $existing = $this->mappings[$key] ?? null;

        if ($existing === null || $mapping->isStrongerThan($existing)) {
            $this->mappings[$key] = $mapping;
        }
    }

    /**
     * @param  iterable<FacetMapping>  $mappings
     */
    public function merge(iterable $mappings): void
    {
        foreach ($mappings as $mapping) {
            $this->add($mapping);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->mappings[$key]);
    }

    public function count(): int
    {
        return count($this->mappings);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            fn (FacetMapping $mapping) => $mapping->toArray(),
            $this->mappings
        ));
    }

    /**
     * @return array<string, FacetMapping>
     */
    public function all(): array
    {
        return $this->mappings;
    }
}
