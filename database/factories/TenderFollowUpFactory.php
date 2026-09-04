<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderFollowUp>
 */
class TenderFollowUpFactory extends Factory
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
            'presentation_scheduled_at' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+1 month') : null,
            'presentation_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'negotiation_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'bid_validity_until' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+3 months') : null,
            'expected_result_date' => fake()->boolean(50) ? fake()->dateTimeBetween('now', '+2 months') : null,
            'expected_result_notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'created_by' => User::factory(),
        ];
    }
}
