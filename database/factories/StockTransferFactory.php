<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'from_location_type' => 'plantel',
            'from_vendedor_id' => null,
            'to_location_type' => 'plantel',
            'to_vendedor_id' => null,
            'note' => null,
            'is_mock' => true,
        ];
    }
}
