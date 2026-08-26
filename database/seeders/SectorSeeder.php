<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    /**
     * Seed a starter set of example sectors.
     *
     * These are example data for local development, not a fixed list — admins
     * can add, rename, or deactivate sectors at any time.
     */
    public function run(): void
    {
        foreach ([
            'Facility Services',
            'Public Administration',
            'Healthcare',
            'Education',
            'Industrial',
            'Unknown',
        ] as $name) {
            Sector::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
