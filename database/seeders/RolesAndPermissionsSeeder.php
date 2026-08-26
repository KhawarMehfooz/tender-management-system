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
     * Default individually-assignable rights granted to each role out of the box.
     * Admins can adjust these afterwards via the Roles & Permissions panel page.
     *
     * @var array<string, list<Right>>
     */
    private const DEFAULT_ROLE_RIGHTS = [
        RoleName::SUPER_ADMIN->value => [
            Right::SEE_PRICES,
            Right::SEE_MARGINS,
            Right::SEE_COMPETITOR_DATA,
            Right::EXECUTE_FINAL_SUBMISSION,
            Right::VIEW_EMPLOYEE_STATISTICS,
        ],
        RoleName::DEPARTMENT_HEAD->value => [
            Right::SEE_PRICES,
            Right::SEE_MARGINS,
            Right::SEE_COMPETITOR_DATA,
            Right::EXECUTE_FINAL_SUBMISSION,
            Right::VIEW_EMPLOYEE_STATISTICS,
        ],
        RoleName::TEAM_LEAD->value => [
            Right::SEE_PRICES,
            Right::SEE_MARGINS,
            Right::EXECUTE_FINAL_SUBMISSION,
        ],
        RoleName::CALCULATION->value => [
            Right::SEE_PRICES,
            Right::SEE_MARGINS,
        ],
    ];

    /**
     * Seed the roles and individually-assignable rights, then apply default grants.
     */
    public function run(): void
    {
        foreach (Right::cases() as $right) {
            Permission::findOrCreate($right->value);
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value);

            $defaultRights = array_map(
                fn (Right $right): string => $right->value,
                self::DEFAULT_ROLE_RIGHTS[$roleName->value] ?? [],
            );

            $role->syncPermissions($defaultRights);
        }
    }
}
