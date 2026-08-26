<?php

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Pages\RolesAndPermissions;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('allows a super admin to view the matrix', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);

    $this->actingAs($admin);

    Livewire::test(RolesAndPermissions::class)
        ->assertSuccessful();
});

it('rejects a user without the super admin role, server-side', function () {
    $user = tap(User::factory()->create())->assignRole(RoleName::STAFF);

    $this->actingAs($user);

    Livewire::test(RolesAndPermissions::class)
        ->assertForbidden();
});

it('grants a right to a role when toggled on', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $staff = Role::findByName(RoleName::STAFF->value);

    $this->actingAs($admin);

    Livewire::test(RolesAndPermissions::class)
        ->call('updateTableColumnState', Right::SEE_PRICES->value, $staff->getKey(), true);

    expect($staff->refresh()->hasPermissionTo(Right::SEE_PRICES->value))
        ->toBeTrue();
});

it('revokes a right from a role when toggled off', function () {
    $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    $staff = Role::findByName(RoleName::STAFF->value);
    $staff->givePermissionTo(Right::SEE_PRICES->value);

    $this->actingAs($admin);

    Livewire::test(RolesAndPermissions::class)
        ->call('updateTableColumnState', Right::SEE_PRICES->value, $staff->getKey(), false);

    expect($staff->refresh()->hasPermissionTo(Right::SEE_PRICES->value))
        ->toBeFalse();
});
