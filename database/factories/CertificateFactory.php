<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(CertificateType::cases()),
            'name' => fake()->words(3, true),
            'issuing_body' => fake()->company(),
            'valid_from' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'expiry_date' => fake()->dateTimeBetween('+6 months', '+2 years'),
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'size' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => now()->addDays(10),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->subYears(2),
            'expiry_date' => now()->subDays(5),
        ]);
    }

    public function withFile(): static
    {
        return $this->state(fn (): array => [
            'file_path' => 'certificates/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 5_000_000),
        ]);
    }
}
