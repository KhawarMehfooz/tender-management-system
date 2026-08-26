<?php

namespace Database\Factories;

use App\Models\ProcurementProcedure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementProcedure>
 */
class ProcurementProcedureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
        ];
    }
}
