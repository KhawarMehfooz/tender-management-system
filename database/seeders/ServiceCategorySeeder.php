<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Seed a starter set of example service categories.
     *
     * These are example data for local development, not a fixed list — admins
     * can add, rename, or deactivate categories at any time.
     */
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Security Services',
                'description' => 'Guarding, access control, and site security tenders.',
                'active' => true,
            ],
            [
                'name' => 'Cleaning Services',
                'description' => 'Facility and industrial cleaning tenders.',
                'active' => true,
            ],
            [
                'name' => 'Facility Management',
                'description' => 'Combined building and grounds maintenance tenders.',
                'active' => true,
            ],
        ] as $category) {
            ServiceCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                $category,
            );
        }
    }
}
