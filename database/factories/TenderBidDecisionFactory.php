<?php

namespace Database\Factories;

use App\Enums\BidDecision;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderBidDecision>
 */
class TenderBidDecisionFactory extends Factory
{
    /**
     * Define the model's default state. Defaults to BID so the factory never randomly throws
     * BidDecisionReasonRequiredException — use the noBid() state for a NO_BID row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tender_id' => Tender::factory(),
            'decision' => BidDecision::BID,
            'reason' => null,
            'score' => fake()->numberBetween(0, 100),
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ];
    }

    public function noBid(): static
    {
        return $this->state(fn (): array => [
            'decision' => BidDecision::NO_BID,
            'reason' => fake()->sentence(),
        ]);
    }
}
