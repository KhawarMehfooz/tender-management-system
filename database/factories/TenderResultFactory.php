<?php

namespace Database\Factories;

use App\Enums\WinLossReason;
use App\Models\Tender;
use App\Models\TenderResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderResult>
 */
class TenderResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $winningPrice = fake()->randomFloat(2, 10000, 500000);
        $ourPrice = fake()->randomFloat(2, 10000, 500000);

        return [
            'tender_id' => Tender::factory(),
            'winner' => fake()->company(),
            'our_rank' => fake()->numberBetween(1, 5),
            'winning_price' => $winningPrice,
            'our_price' => $ourPrice,
            'price_gap' => round($winningPrice - $ourPrice, 2),
            'award_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'known_evaluation' => fake()->paragraph(),
            'reasoning' => fake()->paragraph(),
            'award_decision' => fake()->paragraph(),
            'win_loss_reasons' => fake()->randomElements(
                array_column(WinLossReason::cases(), 'value'),
                fake()->numberBetween(1, 3),
            ),
            'created_by' => User::factory(),
        ];
    }
}
