<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Seed a starter set of example tender sources.
     *
     * These are example data for local development, not a fixed list — admins
     * can add, rename, or deactivate sources at any time.
     */
    public function run(): void
    {
        foreach ([
            'TED',
            'service.bund.de',
            'oeffentlichevergabe.de',
            'Vergabe.NRW',
            'DTVP',
            'subreport ELViS',
            'Direct enquiry',
            'Existing client',
            'Referral',
            'Unknown',
        ] as $name) {
            Source::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
