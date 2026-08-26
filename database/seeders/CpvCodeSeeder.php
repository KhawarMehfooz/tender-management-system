<?php

namespace Database\Seeders;

use App\Models\CpvCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CpvCodeSeeder extends Seeder
{
    /**
     * Seed CPV (Common Procurement Vocabulary) codes.
     *
     * If an official CSV export (code,label columns) has been placed at
     * database/data/cpv_codes.csv, import the full list via the
     * `import:cpv-codes` command. Otherwise fall back to a small subset
     * covering the service categories this app ships with, for local
     * development.
     */
    public function run(): void
    {
        $csvPath = database_path('data/cpv_codes.csv');

        if (File::exists($csvPath)) {
            Artisan::call('import:cpv-codes', ['file' => $csvPath]);

            return;
        }

        foreach ([
            ['code' => '79713000-5', 'label' => 'Guard services'],
            ['code' => '79714000-2', 'label' => 'Surveillance services'],
            ['code' => '79715000-9', 'label' => 'Patrol services'],
            ['code' => '90910000-9', 'label' => 'Cleaning services'],
            ['code' => '90911200-8', 'label' => 'Building-cleaning services'],
            ['code' => '90919200-4', 'label' => 'Office-cleaning services'],
            ['code' => '98341120-2', 'label' => 'Building management services'],
            ['code' => '79993000-1', 'label' => 'Building and facilities management services'],
            ['code' => '00000000-0', 'label' => 'Unknown'],
        ] as $code) {
            CpvCode::query()->updateOrCreate(['code' => $code['code']], [
                'label' => $code['label'],
                'active' => true,
            ]);
        }
    }
}
