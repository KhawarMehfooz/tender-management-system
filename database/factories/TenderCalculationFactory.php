<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderCalculation>
 */
class TenderCalculationFactory extends Factory
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
            'version_number' => 1,
            'created_by' => User::factory(),
            'input_values' => [],
        ];
    }
}
