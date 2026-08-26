<?php

namespace Database\Factories;

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Models\TenderStatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderStatusChange>
 */
class TenderStatusChangeFactory extends Factory
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
            'from_status' => TenderStatus::INTAKE,
            'to_status' => TenderStatus::REVIEW,
            'changed_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'changed_at' => now(),
        ];
    }
}
