<?php

namespace Database\Factories;

use App\Models\TenderSubmission;
use App\Models\TenderSubmissionFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderSubmissionFile>
 */
class TenderSubmissionFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_submission_id' => TenderSubmission::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'tender-submission-files/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 5_000_000),
        ];
    }
}
