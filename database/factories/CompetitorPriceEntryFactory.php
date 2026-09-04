<?php

namespace Database\Factories;

use App\Models\Competitor;
use App\Models\CompetitorPriceEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitorPriceEntry>
 */
class CompetitorPriceEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competitor_id' => Competitor::factory(),
            'price' => fake()->randomFloat(2, 10, 500),
            'source' => fake()->sentence(),
            'observed_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'context' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
