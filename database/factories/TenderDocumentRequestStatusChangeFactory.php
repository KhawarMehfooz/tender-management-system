<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Models\TenderDocumentRequest;
use App\Models\TenderDocumentRequestStatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocumentRequestStatusChange>
 */
class TenderDocumentRequestStatusChangeFactory extends Factory
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
            'from_status' => DocumentRequestStatus::OPEN,
            'to_status' => DocumentRequestStatus::IN_PROGRESS,
            'changed_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'changed_at' => now(),
        ];
    }
}
