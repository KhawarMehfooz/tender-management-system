<?php

namespace Database\Seeders;

use App\Models\NutsCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class NutsCodeSeeder extends Seeder
{
    /**
     * Seed NUTS (Nomenclature of Territorial Units for Statistics) codes.
     *
     * If an official Eurostat CSV export (code,label,level,parent_code
     * columns) has been placed at database/data/nuts_codes.csv, import the
     * full hierarchy via the `import:nuts-codes` command. Otherwise fall
     * back to Germany's NUTS 1 (federal states) for local development.
     */
    public function run(): void
    {
        $csvPath = database_path('data/nuts_codes.csv');

        if (File::exists($csvPath)) {
            Artisan::call('import:nuts-codes', ['file' => $csvPath]);

            return;
        }

        $germany = NutsCode::query()->updateOrCreate(['code' => 'DE'], [
            'label' => 'Deutschland',
            'level' => 0,
            'parent_id' => null,
            'active' => true,
        ]);

        foreach ([
            'DE1' => 'Baden-Württemberg',
            'DE2' => 'Bayern',
            'DE3' => 'Berlin',
            'DE4' => 'Brandenburg',
            'DE5' => 'Bremen',
            'DE6' => 'Hamburg',
            'DE7' => 'Hessen',
            'DE8' => 'Mecklenburg-Vorpommern',
            'DE9' => 'Niedersachsen',
            'DEA' => 'Nordrhein-Westfalen',
            'DEB' => 'Rheinland-Pfalz',
            'DEC' => 'Saarland',
            'DED' => 'Sachsen',
            'DEE' => 'Sachsen-Anhalt',
            'DEF' => 'Schleswig-Holstein',
            'DEG' => 'Thüringen',
        ] as $code => $label) {
            NutsCode::query()->updateOrCreate(['code' => $code], [
                'label' => $label,
                'level' => 1,
                'parent_id' => $germany->id,
                'active' => true,
            ]);
        }

        NutsCode::query()->updateOrCreate(['code' => 'UNKNOWN'], [
            'label' => 'Unknown',
            'level' => 0,
            'parent_id' => null,
            'active' => true,
        ]);
    }
}
