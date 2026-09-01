<?php

namespace Database\Factories;

use App\Enums\ConceptBlockCategory;
use App\Models\ConceptBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptBlock>
 */
class ConceptBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ConceptBlockCategory::cases()),
            'title' => fake()->sentence(3),
            'created_by' => User::factory(),
        ];
    }
}
