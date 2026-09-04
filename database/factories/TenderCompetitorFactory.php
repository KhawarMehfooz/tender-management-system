<?php

namespace Database\Factories;

use App\Enums\CompetitorOutcome;
use App\Models\Competitor;
use App\Models\Tender;
use App\Models\TenderCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderCompetitor>
 */
class TenderCompetitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'competitor_id' => Competitor::factory(),
            'outcome' => fake()->randomElement(CompetitorOutcome::cases()),
            'known_price' => fake()->optional()->randomFloat(2, 10000, 500000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
