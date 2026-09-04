<?php

namespace Database\Factories;

use App\Models\Reference;
use App\Models\ReferenceAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceAttachment>
 */
class ReferenceAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_id' => Reference::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'reference-attachments/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 5_000_000),
        ];
    }
}
