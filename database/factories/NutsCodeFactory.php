<?php

namespace Database\Factories;

use App\Models\NutsCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutsCode>
 */
class NutsCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('NUTS-????'),
            'label' => fake()->words(2, true),
            'level' => 0,
            'parent_id' => null,
            'active' => true,
        ];
    }
}
