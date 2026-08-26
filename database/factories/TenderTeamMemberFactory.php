<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Tender;
use App\Models\TenderTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenderTeamMember>
 */
class TenderTeamMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'functional_role' => fake()->randomElement(TeamRole::cases()),
        ];
    }
}
