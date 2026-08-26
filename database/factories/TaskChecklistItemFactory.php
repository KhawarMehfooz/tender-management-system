<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskChecklistItem>
 */
class TaskChecklistItemFactory extends Factory
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
            'description' => fake()->sentence(3),
            'is_done' => false,
            'position' => 0,
        ];
    }
}
