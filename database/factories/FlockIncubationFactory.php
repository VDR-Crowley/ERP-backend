<?php

namespace Database\Factories;

use App\Models\FlockIncubation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlockIncubation>
 */
class FlockIncubationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'start_date' => now()->subDays(10)->toDateString(),
            'species' => 'quail',
            'egg_count' => 150,
            'expected_hatch_date' => now()->addDays(8)->toDateString(),
            'status' => 'incubando',
            'egg_cost' => fake()->randomFloat(2, 50, 200),
            'feed_cost' => fake()->randomFloat(2, 20, 100),
            'notes' => null,
        ];
    }
}
