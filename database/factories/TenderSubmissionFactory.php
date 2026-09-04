<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderSubmission>
 */
class TenderSubmissionFactory extends Factory
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
            'submission_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'submission_time' => fake()->time(),
            'responsible_employee_id' => User::factory(),
            'portal' => fake()->randomElement(['e-Vergabe', 'TED eTendering', 'Subreport', 'Vergabe24']),
            'transmission_route' => fake()->randomElement(['Electronic portal upload', 'Email', 'Postal']),
            'receipt_confirmed' => fake()->boolean(60),
            'receipt_confirmed_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'created_by' => User::factory(),
        ];
    }
}
