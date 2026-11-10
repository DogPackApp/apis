<?php

namespace App\Services\Product;

use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Repository\Product\ProductRepository;

class ProductService
{
    private ?Store $store = null;

    public function __construct(private readonly ProductRepository $productRepository) {}

    public function setStore(Store $store): void
    {
        $this->store = $store;
    }

    public function createProduct(Store|array $store, ?array $data = null): Item
    {
        if (is_array($store)) {
            return $this->productRepository->create($this->store, $store);
        }

        return $this->productRepository->create($store, $data ?? []);
    }

    public function updateProduct(Store|Item $store, Item|array $item, ?array $data = null): Item
    {
        if ($store instanceof Item) {
            return $this->productRepository->update($this->store, $store, $item);
        }

        return $this->productRepository->update($store, $item, $data ?? []);
    }
}
