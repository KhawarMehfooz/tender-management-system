<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Models\Tender;
use App\Models\TenderDocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocumentRequest>
 */
class TenderDocumentRequestFactory extends Factory
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
            'tender_communication_id' => null,
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'deadline' => fake()->boolean(60) ? fake()->dateTimeBetween('now', '+1 month') : null,
            'status' => DocumentRequestStatus::OPEN,
            'created_by' => User::factory(),
        ];
    }
}
