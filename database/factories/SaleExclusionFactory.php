<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleExclusion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleExclusion>
 */
class SaleExclusionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'reason' => fake()->sentence(),
        ];
    }
}
