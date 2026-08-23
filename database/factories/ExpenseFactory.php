<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'description' => fake()->sentence(3),
            'category' => 'Geral',
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 10, 300),
            'paid' => true,
            'is_mock' => true,
        ];
    }
}
