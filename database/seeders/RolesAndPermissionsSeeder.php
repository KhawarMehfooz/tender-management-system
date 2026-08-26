<?php

namespace Database\Seeders;

use App\Enums\Right;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the roles and individually-assignable rights.
     */
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value);
        }

        foreach (Right::cases() as $right) {
            Permission::findOrCreate($right->value);
        }
    }
}
