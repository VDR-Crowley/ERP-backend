<?php

namespace Database\Factories;

use App\Models\FeedOpenLog;
use App\Models\FeedStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedOpenLog>
 */
class FeedOpenLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'feed_stock_id' => FeedStock::factory(),
            'feed_type' => fake()->word(),
            'date' => now()->toDateString(),
            'weight_kg' => 40,
        ];
    }
}
