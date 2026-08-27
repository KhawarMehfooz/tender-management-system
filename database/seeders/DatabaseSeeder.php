<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ServiceCategorySeeder::class);
        $this->call(SourceSeeder::class);
        $this->call(SectorSeeder::class);
        $this->call(ProcurementProcedureSeeder::class);
        $this->call(CpvCodeSeeder::class);
        $this->call(NutsCodeSeeder::class);

        // Demo users (admin@example.com and one known-credential account per role) plus a full
        // set of realistic tenders/tasks/teams — local/testing only, never production, since
        // every demo account uses the well-known password "password".
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
