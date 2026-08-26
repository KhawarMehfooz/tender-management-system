<?php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\Source;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tender>
 */
class TenderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'procurement_number' => fake()->optional()->bothify('##/####-??'),
            'contracting_authority' => fake()->company(),
            'procurement_office' => fake()->optional()->city(),
            'contact_person' => fake()->optional()->name(),
            'contact_email' => fake()->optional()->safeEmail(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'city' => fake()->optional()->city(),
            'service_category_id' => ServiceCategory::factory(),
            'sector_id' => Sector::factory(),
            'procurement_procedure_id' => ProcurementProcedure::factory(),
            'estimated_contract_volume' => fake()->randomFloat(2, 10000, 2000000),
            'estimated_contract_volume_unknown' => false,
            'contract_term' => fake()->optional()->numerify('## months'),
            'contract_start_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'contract_end_date' => fake()->optional()->dateTimeBetween('+1 year', '+3 years'),
            'extension_options' => fake()->optional()->sentence(),
            'submission_deadline' => fake()->dateTimeBetween('+2 weeks', '+3 months'),
            'bidder_question_deadline' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'site_visit_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'bid_validity_days' => fake()->optional()->numberBetween(30, 180),
            'publication_date' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'source_id' => Source::factory(),
            'portal_link' => fake()->optional()->url(),
            'notes' => fake()->optional()->paragraph(),
            'owner_id' => User::factory(),
            'status' => TenderStatus::INTAKE,
        ];
    }
}
