<?php

namespace Database\Factories;

use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConceptBlockVersion>
 */
class ConceptBlockVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'concept_block_id' => ConceptBlock::factory(),
            'version_number' => 1,
            'content' => fake()->paragraphs(3, true),
            'created_by' => User::factory(),
        ];
    }
}
