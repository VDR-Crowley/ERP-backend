<?php

namespace Database\Factories;

use App\Models\FeedStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedStock>
 */
class FeedStockFactory extends Factory
{
    public function definition(): array
    {
        $bags = fake()->numberBetween(1, 20);
        $weight = 40;

        return [
            'type' => fake()->unique()->word(),
            'bags_in_stock' => $bags,
            'kg_in_stock' => $bags * $weight,
            'last_bag_weight_kg' => $weight,
            'expiration_date' => now()->addMonths(3)->toDateString(),
        ];
    }
}
