<?php

namespace Database\Factories;

use App\Enums\AbsenceType;
use App\Models\User;
use App\Models\UserAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAbsence>
 */
class UserAbsenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(AbsenceType::cases()),
            'starts_at' => $startsAt,
            'ends_at' => fake()->dateTimeBetween($startsAt, (clone $startsAt)->modify('+2 weeks')),
            'notes' => fake()->optional()->sentence(),
            'cover_user_id' => null,
        ];
    }
}
