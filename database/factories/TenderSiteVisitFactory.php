<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderSiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderSiteVisit>
 */
class TenderSiteVisitFactory extends Factory
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
            'visit_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'attendees' => fake()->name().', '.fake()->name(),
            'contact_person' => fake()->boolean(70) ? fake()->name() : null,
            'access_routes' => fake()->boolean(50) ? fake()->sentence() : null,
            'parking' => fake()->boolean(50) ? fake()->sentence() : null,
            'areas' => fake()->boolean(50) ? fake()->sentence() : null,
            'risks' => fake()->boolean(30) ? fake()->sentence() : null,
            'technical_particularities' => fake()->boolean(30) ? fake()->sentence() : null,
            'staffing_requirement' => fake()->boolean(40) ? fake()->sentence() : null,
            'competitors_spotted' => fake()->boolean(20) ? fake()->company() : null,
            'open_questions' => fake()->boolean(30) ? fake()->sentence() : null,
            'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'created_by' => User::factory(),
        ];
    }
}
