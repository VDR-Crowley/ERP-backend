<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorStock>
 */
class VendorStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'vendedor_id' => Vendedor::factory(),
            'quantity' => fake()->numberBetween(0, 50),
            'is_mock' => true,
        ];
    }
}
