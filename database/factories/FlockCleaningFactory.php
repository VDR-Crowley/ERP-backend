<?php

namespace Database\Factories;

use App\Models\FlockCleaning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlockCleaning>
 */
class FlockCleaningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'species' => 'quail',
            'cleaning_type' => 'total',
            'notes' => null,
            'is_mock' => true,
        ];
    }
}
