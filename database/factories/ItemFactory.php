<?php

namespace Database\Factories;

use App\Models\Product\Item;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => Item::TYPES[Item::TYPE_PHYSICAL],
            'title' => fake()->words(3, true),
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'origin' => 'US',
            'status' => Item::STATUS[Item::STATUS_DRAFT],
        ];
    }
}
