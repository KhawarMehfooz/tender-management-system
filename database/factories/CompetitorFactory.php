<?php

namespace Database\Factories;

use App\Models\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competitor>
 */
class CompetitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'region' => fake()->randomElement(['Bavaria', 'North Rhine-Westphalia', 'Berlin', 'Hamburg', 'Saxony']),
            'service_areas' => fake()->words(3, true),
            'known_clients' => fake()->words(3, true),
            'strengths' => fake()->sentence(),
            'weaknesses' => fake()->sentence(),
            'market_segments' => fake()->words(2, true),
            'internal_notes' => null,
        ];
    }
}
