<?php

namespace Database\Factories;

use App\Models\EggStock;
use Illuminate\Database\Eloquent\Factories\Factory;

class EggStockFactory extends Factory
{
    protected $model = EggStock::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'quail_eggs' => $this->faker->numberBetween(0, 500),
            'chicken_eggs' => $this->faker->numberBetween(0, 500),
            'quail_packs' => $this->faker->randomFloat(2, 0, 50),
            'chicken_packs' => $this->faker->randomFloat(2, 0, 50),
            'quail_stock_value' => $this->faker->randomFloat(2, 0, 500),
            'chicken_stock_value' => $this->faker->randomFloat(2, 0, 500),
        ];
    }
}
