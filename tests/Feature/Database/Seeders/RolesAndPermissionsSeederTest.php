<?php

use App\Enums\Right;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

it('creates every role and right defined by the enums', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }

    foreach (Right::cases() as $right) {
        expect(Permission::where('name', $right->value)->exists())->toBeTrue();
    }
});

it('assigns UUID primary keys, not sequential integers', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::first()->id)->toBeString()->toHaveLength(36);
    expect(Permission::first()->id)->toBeString()->toHaveLength(36);
});

it('is safe to run twice without duplicating rows', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleName::cases()));
    expect(Permission::count())->toBe(count(Right::cases()));
});

it('grants the default rights to super admin and department head', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach ([RoleName::SUPER_ADMIN, RoleName::DEPARTMENT_HEAD] as $roleName) {
        $role = Role::findByName($roleName->value);

        foreach (Right::cases() as $right) {
            expect($role->hasPermissionTo($right->value))->toBeTrue();
        }
    }
});

it('grants only the pricing-related default rights to calculation', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName(RoleName::CALCULATION->value);

    expect($role->hasPermissionTo(Right::SEE_PRICES->value))->toBeTrue();
    expect($role->hasPermissionTo(Right::SEE_MARGINS->value))->toBeTrue();
    expect($role->hasPermissionTo(Right::SEE_COMPETITOR_DATA->value))->toBeFalse();
    expect($role->hasPermissionTo(Right::EXECUTE_FINAL_SUBMISSION->value))->toBeFalse();
    expect($role->hasPermissionTo(Right::VIEW_EMPLOYEE_STATISTICS->value))->toBeFalse();
    expect($role->hasPermissionTo(Right::MAKE_BID_DECISION->value))->toBeFalse();
    expect($role->hasPermissionTo(Right::MANAGE_CERTIFICATES->value))->toBeFalse();
});

it('grants the bid decision right to team lead alongside its other defaults', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName(RoleName::TEAM_LEAD->value);

    expect($role->hasPermissionTo(Right::MAKE_BID_DECISION->value))->toBeTrue();
});

it('does not grant the manage certificates right to team lead', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName(RoleName::TEAM_LEAD->value);

    expect($role->hasPermissionTo(Right::MANAGE_CERTIFICATES->value))->toBeFalse();
});

it('grants no default rights to staff', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName(RoleName::STAFF->value);

    foreach (Right::cases() as $right) {
        expect($role->hasPermissionTo($right->value))->toBeFalse();
    }
});
