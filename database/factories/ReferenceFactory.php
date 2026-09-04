<?php

namespace Database\Factories;

use App\Models\Reference;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client' => fake()->company(),
            'service_category_id' => ServiceCategory::factory(),
            'sector_id' => Sector::factory(),
            'location' => fake()->city(),
            'period_start' => fake()->dateTimeBetween('-3 years', '-1 year'),
            'period_end' => fake()->dateTimeBetween('-1 year', 'now'),
            'contract_volume' => fake()->randomFloat(2, 20_000, 2_000_000),
            'contract_volume_unknown' => false,
            'headcount' => fake()->numberBetween(1, 200),
            'contact_person_name' => fake()->name(),
            'contact_person_email' => fake()->safeEmail(),
            'contact_person_phone' => fake()->phoneNumber(),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Contract volume is unknown — the toggle is set and the amount is cleared, mirroring
     * Tender::estimated_contract_volume_unknown's convention.
     */
    public function volumeUnknown(): static
    {
        return $this->state(fn (): array => [
            'contract_volume' => null,
            'contract_volume_unknown' => true,
        ]);
    }
}
