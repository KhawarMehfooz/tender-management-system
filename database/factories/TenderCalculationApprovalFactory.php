<?php

namespace Database\Factories;

use App\Enums\CalculationApprovalStep;
use App\Models\TenderCalculation;
use App\Models\TenderCalculationApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderCalculationApproval>
 */
class TenderCalculationApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_calculation_id' => TenderCalculation::factory(),
            'step' => fake()->randomElement(CalculationApprovalStep::cases()),
            'approved_by' => null,
            'approved_at' => null,
            'comment' => null,
        ];
    }
}
