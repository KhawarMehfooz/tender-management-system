<?php

namespace Database\Factories;

use App\Models\CpvCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpvCode>
 */
class CpvCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('########-#'),
            'label' => fake()->words(3, true),
            'active' => true,
        ];
    }
}
