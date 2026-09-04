<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderLessonsLearned;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderLessonsLearned>
 */
class TenderLessonsLearnedFactory extends Factory
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
            'went_well' => fake()->paragraph(),
            'differently_next_time' => fake()->paragraph(),
            'process_changes' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
