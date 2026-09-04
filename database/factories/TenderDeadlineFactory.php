<?php

namespace Database\Factories;

use App\Enums\DeadlineType;
use App\Models\Tender;
use App\Models\TenderDeadline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDeadline>
 */
class TenderDeadlineFactory extends Factory
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
            'type' => fake()->randomElement(DeadlineType::cases()),
            'due_at' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ];
    }
}
