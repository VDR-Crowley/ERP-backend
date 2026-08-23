<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseSpeciesOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseSpeciesOverride>
 */
class ExpenseSpeciesOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'species' => 'quail',
            'reason' => fake()->sentence(),
        ];
    }
}
