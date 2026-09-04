<?php

namespace Database\Factories;

use App\Models\TenderDocumentRequest;
use App\Models\TenderDocumentRequestFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocumentRequestFile>
 */
class TenderDocumentRequestFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_document_request_id' => TenderDocumentRequest::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'tender-document-request-files/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
