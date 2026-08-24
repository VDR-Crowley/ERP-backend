<?php

namespace Database\Factories;

use App\Models\DailyProduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyProduction>
 */
class DailyProductionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'quail_eggs' => fake()->numberBetween(0, 200),
            'chicken_eggs' => fake()->numberBetween(0, 200),
            'is_mock' => true,
        ];
    }
}
