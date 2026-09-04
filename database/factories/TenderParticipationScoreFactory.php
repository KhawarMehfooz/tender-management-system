<?php

namespace Database\Factories;

use App\Models\Tender;
use App\Models\TenderParticipationScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderParticipationScore>
 */
class TenderParticipationScoreFactory extends Factory
{
    /**
     * Define the model's default state. Ratings are left unset (null) by default, matching
     * a freshly-created participation score before any manual rating has been entered.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
        ];
    }

    /**
     * All 7 manual ratings set to the given value (default: 3, neutral), so score() resolves.
     */
    public function rated(int $rating = 3): static
    {
        return $this->state(fn (): array => array_fill_keys(TenderParticipationScore::MANUAL_RATING_FIELDS, $rating));
    }
}
