<?php

namespace Database\Factories;

use App\Enums\CostDriverFieldType;
use App\Models\ServiceCategory;
use App\Models\ServiceCategoryCostDriverField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategoryCostDriverField>
 */
class ServiceCategoryCostDriverFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'field_key' => fake()->unique()->slug(2, '_'),
            'label' => fake()->words(2, true),
            'type' => fake()->randomElement(CostDriverFieldType::cases()),
            'unit' => fake()->optional()->randomElement(['h', 'm²', '%', 'km']),
            'required' => true,
            'order' => 0,
        ];
    }
}
