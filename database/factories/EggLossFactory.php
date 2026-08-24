<?php

namespace Database\Factories;

use App\Models\EggLoss;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EggLoss>
 */
class EggLossFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'species' => 'quail',
            'quantity' => fake()->numberBetween(1, 100),
            'reason' => 'quebrado',
            'is_mock' => true,
        ];
    }
}
