<?php

namespace App\Services\Product;

use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Repository\Product\ProductRepository;

class ProductService
{
    public function __construct(private readonly ProductRepository $productRepository) {}

    public function createProduct(Store $store, array $data): Item
    {
        return $this->productRepository->create($store, $data);
    }

    public function updateProduct(Store $store, Item $item, array $data): Item
    {
        return $this->productRepository->update($store, $item, $data);
    }
}
