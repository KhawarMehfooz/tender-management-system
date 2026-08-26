<?php

use App\Enums\Right;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('denies an ungranted right to a user without the super-admin role', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows(Right::SEE_MARGINS->value))->toBeFalse();
});

it('grants every right to a super-admin even without it being individually assigned', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::SUPER_ADMIN);

    expect(Gate::forUser($user)->allows(Right::SEE_MARGINS->value))->toBeTrue();
});

it('still respects an individually granted right for a non-super-admin user', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Right::SEE_MARGINS);

    expect(Gate::forUser($user)->allows(Right::SEE_MARGINS->value))->toBeTrue();
});
