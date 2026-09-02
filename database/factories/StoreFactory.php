<?php

namespace Database\Factories;

use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'status' => 1,
            'states' => Store::STATES_ACTIVE,
            'timezone' => 'UTC',
        ];
    }
}
