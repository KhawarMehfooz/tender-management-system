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
                'code' => 'SEC',
                'description' => 'Guarding, access control, and site security tenders.',
                'active' => true,
            ],
            [
                'name' => 'Cleaning Services',
                'code' => 'CLN',
                'description' => 'Facility and industrial cleaning tenders.',
                'active' => true,
            ],
            [
                'name' => 'Facility Management',
                'code' => 'FM',
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
