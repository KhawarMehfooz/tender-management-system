<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'owner_id' => User::factory(),
            'creator_id' => User::factory(),
            'reviewer_id' => fake()->optional()->passthrough(User::factory()),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'start_date' => fake()->optional()->dateTimeBetween('-1 week', '+1 week'),
            'due_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
        ];
    }
}
