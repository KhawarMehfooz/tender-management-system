<?php

use App\Enums\Right;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access', function () {
    it('allows a super admin to view the user list', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)->assertSuccessful();
    });

    it('rejects a non-super-admin from the list, create, and edit pages, server-side', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $otherUser = User::factory()->create();

        $this->actingAs($staff);

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(CreateUser::class)->assertForbidden();
        Livewire::test(EditUser::class, ['record' => $otherUser->getRouteKey()])->assertForbidden();
    });

    it('never authorizes deleting a user, even for a super admin', function () {
        $user = User::factory()->create();

        expect(UserResource::canDelete($user))->toBeFalse();
        expect(UserResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});

describe('creation', function () {
    it('creates a user with a role, category, and individual rights', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $category = ServiceCategory::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'role' => RoleName::STAFF->value,
                'service_category_id' => $category->id,
                Right::SEE_PRICES->value => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'jane@example.com')->firstOrFail();

        expect($created->hasRole(RoleName::STAFF))->toBeTrue();
        expect($created->service_category_id)->toBe($category->id);
        expect($created->hasDirectPermission(Right::SEE_PRICES->value))->toBeTrue();
        expect($created->password)->not->toBe('password123');
    });

    it('rejects an email that duplicates an existing user', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'role' => RoleName::STAFF->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    });
});

describe('editing', function () {
    it('updates role, category, and rights, and preserves the password when left blank', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $category = ServiceCategory::factory()->create();
        $user = tap(User::factory()->create())->assignRole(RoleName::VIEWER);
        $user->givePermissionTo(Right::SEE_PRICES->value);
        $originalPassword = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'password' => '',
                'role' => RoleName::TEAM_LEAD->value,
                'service_category_id' => $category->id,
                Right::SEE_MARGINS->value => true,
                Right::SEE_PRICES->value => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        expect($user->hasRole(RoleName::TEAM_LEAD))->toBeTrue();
        expect($user->hasRole(RoleName::VIEWER))->toBeFalse();
        expect($user->service_category_id)->toBe($category->id);
        expect($user->hasDirectPermission(Right::SEE_MARGINS->value))->toBeTrue();
        expect($user->hasDirectPermission(Right::SEE_PRICES->value))->toBeFalse();
        expect($user->password)->toBe($originalPassword);
    });

    it('changes the password when a new one is submitted', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $user = tap(User::factory()->create())->assignRole(RoleName::VIEWER);
        $originalPassword = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'password' => 'newpassword123',
                'role' => RoleName::VIEWER->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->refresh()->password)->not->toBe($originalPassword);
    });
});
