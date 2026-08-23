<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'unit' => 'Bandeja (30 ovos)',
            'unit_price' => fake()->randomFloat(2, 5, 50),
            'stock' => fake()->numberBetween(0, 100),
            'eggs_per_unit' => 30,
            'is_mock' => true,
        ];
    }
}
