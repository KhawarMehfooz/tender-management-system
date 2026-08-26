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
