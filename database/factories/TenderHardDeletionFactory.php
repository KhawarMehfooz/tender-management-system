<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderHardDeletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderHardDeletion>
 */
class TenderHardDeletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tender = Tender::factory()->create();

        return [
            'tender_id' => $tender->id,
            'internal_id' => $tender->internal_id,
            'title' => $tender->title,
            'deleted_by' => User::factory(),
            'reason' => fake()->sentence(),
            'deleted_at' => now(),
        ];
    }
}
