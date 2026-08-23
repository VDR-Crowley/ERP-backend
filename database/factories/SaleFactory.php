<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 5, 50);

        return [
            'date' => now()->toDateString(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
            'payment_pending' => false,
            'buyer' => fake()->name(),
            'seller_id' => Vendedor::factory(),
            'delivery_pending' => false,
            'delivery_date' => null,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
            'is_mock' => true,
        ];
    }
}
