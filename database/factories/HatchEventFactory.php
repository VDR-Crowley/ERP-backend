<?php

namespace Database\Factories;

use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HatchEvent>
 */
class HatchEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flock_incubation_id' => FlockIncubation::factory(),
            'date' => now()->toDateString(),
            'count' => fake()->numberBetween(10, 50),
            'notes' => null,
            'is_mock' => true,
        ];
    }
}
