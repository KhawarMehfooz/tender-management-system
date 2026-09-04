<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'region' => fake()->randomElement(['Bavaria', 'North Rhine-Westphalia', 'Berlin', 'Hamburg', 'Saxony']),
            'notes' => null,
            'active' => true,
        ];
    }
}
