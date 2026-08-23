<?php

namespace Database\Factories;

use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendedor>
 */
class VendedorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'contact' => fake()->phoneNumber(),
            'active' => true,
            'is_mock' => true,
        ];
    }
}
