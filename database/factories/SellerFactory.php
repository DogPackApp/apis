<?php

namespace Database\Factories;

use App\Models\Seller\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => 0,
            'google_id' => null,
            'twofa_enabled' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 1,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'twofa_enabled' => 1,
        ]);
    }
}
