<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
        $this->call(CpvCodeSeeder::class);
        $this->call(NutsCodeSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $admin->assignRole(RoleName::SUPER_ADMIN);
        }
    }
}
