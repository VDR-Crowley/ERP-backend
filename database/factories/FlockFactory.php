<?php

namespace Database\Factories;

use App\Models\Flock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flock>
 */
class FlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'species' => 'Codornas',
            'quantity' => fake()->numberBetween(10, 200),
            'feed_bags_per_month' => fake()->numberBetween(1, 5),
            'bag_price' => fake()->randomFloat(2, 50, 150),
            'monthly_total' => fake()->randomFloat(2, 100, 500),
            'is_mock' => true,
        ];
    }
}
