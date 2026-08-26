<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskStatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatusChange>
 */
class TaskStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'from_status' => TaskStatus::OPEN,
            'to_status' => TaskStatus::IN_PROGRESS,
            'changed_by' => User::factory(),
            'reason' => fake()->optional()->sentence(),
            'changed_at' => now(),
        ];
    }
}
