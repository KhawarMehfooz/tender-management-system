<?php

namespace Database\Factories;

use App\Models\TenderSiteVisit;
use App\Models\TenderSiteVisitPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderSiteVisitPhoto>
 */
class TenderSiteVisitPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_site_visit_id' => TenderSiteVisit::factory(),
            'uploaded_by' => User::factory(),
            'file_path' => 'tender-site-visit-photos/'.fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 5_000_000),
        ];
    }
}
