<?php

namespace Database\Seeders;

use App\Enums\Right;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

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
