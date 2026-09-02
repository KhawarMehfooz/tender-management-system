<?php

namespace Database\Factories;

use App\Enums\CommunicationType;
use App\Models\Tender;
use App\Models\TenderCommunication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderCommunication>
 */
class TenderCommunicationFactory extends Factory
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
            'type' => fake()->randomElement(CommunicationType::cases()),
            'subject' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'contact_person' => fake()->boolean(60) ? fake()->name() : null,
            'occurred_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'logged_by' => User::factory(),
        ];
    }
}
