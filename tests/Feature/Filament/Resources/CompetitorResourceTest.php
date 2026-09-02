<?php

use App\Enums\RoleName;
use App\Filament\Resources\Competitors\Pages\CreateCompetitor;
use App\Filament\Resources\Competitors\Pages\EditCompetitor;
use App\Filament\Resources\Competitors\Pages\ListCompetitors;
use App\Filament\Resources\Competitors\Pages\ViewCompetitor;
use App\Filament\Resources\Competitors\RelationManagers\PriceEntriesRelationManager;
use App\Models\Competitor;
use App\Models\CompetitorPriceEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('access', function () {
    it('allows a see-competitor-data holder to view the list, create, and edit pages', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $competitor = Competitor::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListCompetitors::class)->assertSuccessful();
        Livewire::test(CreateCompetitor::class)->assertSuccessful();
        Livewire::test(EditCompetitor::class, ['record' => $competitor->getRouteKey()])->assertSuccessful();
    });

    it('rejects a user without the right from the list, create, and edit pages, server-side', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $competitor = Competitor::factory()->create();

        $this->actingAs($staff);

        Livewire::test(ListCompetitors::class)->assertForbidden();
        Livewire::test(CreateCompetitor::class)->assertForbidden();
        Livewire::test(EditCompetitor::class, ['record' => $competitor->getRouteKey()])->assertForbidden();
    });
});

describe('creation', function () {
    it('creates a competitor with valid data', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $this->actingAs($admin);

        Livewire::test(CreateCompetitor::class)
            ->fillForm([
                'name' => 'Rival Services GmbH',
                'region' => 'Bavaria',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Competitor::where('name', 'Rival Services GmbH')->exists())->toBeTrue();
    });

    it('rejects a name that duplicates an existing competitor', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        Competitor::factory()->create(['name' => 'Rival Services GmbH']);

        $this->actingAs($admin);

        Livewire::test(CreateCompetitor::class)
            ->fillForm(['name' => 'Rival Services GmbH'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });
});

describe('price entries relation manager', function () {
    it('requires a source on a new price entry', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $competitor = Competitor::factory()->create();

        $this->actingAs($admin);

        Livewire::test(PriceEntriesRelationManager::class, ['ownerRecord' => $competitor, 'pageClass' => ViewCompetitor::class])
            ->callTableAction('create', data: [
                'price' => 42.50,
                'source' => null,
            ])
            ->assertHasTableActionErrors(['source' => 'required']);
    });

    it('creates a price entry and stamps the creator', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $competitor = Competitor::factory()->create();

        $this->actingAs($admin);

        Livewire::test(PriceEntriesRelationManager::class, ['ownerRecord' => $competitor, 'pageClass' => ViewCompetitor::class])
            ->callTableAction('create', data: [
                'price' => 42.50,
                'source' => 'Award notice, official gazette',
                'observed_on' => now()->subMonth()->format('Y-m-d'),
            ])
            ->assertHasNoTableActionErrors();

        $entry = CompetitorPriceEntry::where('competitor_id', $competitor->id)->first();
        expect($entry)->not->toBeNull();
        expect($entry->source)->toBe('Award notice, official gazette');
        expect($entry->created_by)->toBe($admin->id);
    });

    it('offers no delete action, only edit', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $competitor = Competitor::factory()->create();

        $this->actingAs($admin);

        $entry = CompetitorPriceEntry::factory()->create(['competitor_id' => $competitor->id]);

        Livewire::test(PriceEntriesRelationManager::class, ['ownerRecord' => $competitor, 'pageClass' => ViewCompetitor::class])
            ->assertTableActionDoesNotExist('delete', record: $entry)
            ->assertTableActionVisible('edit', $entry);
    });

    it('hides the create action for a user without the right', function () {
        $staff = tap(User::factory()->create())->assignRole(RoleName::STAFF);
        $competitor = Competitor::factory()->create();

        $this->actingAs($staff);

        Livewire::test(PriceEntriesRelationManager::class, ['ownerRecord' => $competitor, 'pageClass' => ViewCompetitor::class])
            ->assertTableActionHidden('create');
    });
});

describe('deletion', function () {
    it('offers a delete action on the edit page for a user with the right', function () {
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $competitor = Competitor::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditCompetitor::class, ['record' => $competitor->getRouteKey()])
            ->assertActionExists('delete');
    });
});
