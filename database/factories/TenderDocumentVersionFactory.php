<?php

namespace Database\Factories;

use App\Models\TenderDocument;
use App\Models\TenderDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocumentVersion>
 */
class TenderDocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_document_id' => TenderDocument::factory(),
            'version_number' => 1,
            'file_path' => 'tender-documents/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 5_000_000),
            'uploaded_by' => User::factory(),
        ];
    }
}
