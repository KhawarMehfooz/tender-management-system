<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderDocument>
 */
class TenderDocumentFactory extends Factory
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
            'category' => fake()->randomElement(DocumentCategory::cases()),
            'title' => fake()->sentence(3),
            'created_by' => User::factory(),
        ];
    }
}
