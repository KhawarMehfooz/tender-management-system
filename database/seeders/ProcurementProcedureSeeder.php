<?php

namespace Database\Seeders;

use App\Models\ProcurementProcedure;
use Illuminate\Database\Seeder;

class ProcurementProcedureSeeder extends Seeder
{
    /**
     * Seed a starter set of example procurement procedure types.
     *
     * These are example data for local development, not a fixed list — admins
     * can add, rename, or deactivate procedure types at any time.
     */
    public function run(): void
    {
        foreach ([
            'Open procedure',
            'Restricted procedure',
            'Negotiated procedure with prior notice',
            'Negotiated procedure without prior notice',
            'Competitive dialogue',
            'Innovation partnership',
            'Direct award',
            'Unknown',
        ] as $name) {
            ProcurementProcedure::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
