<?php

use App\Filament\Resources\ServiceCategories\Pages\CreateServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates a service category with valid data', function () {
        Livewire::test(CreateServiceCategory::class)
            ->fillForm([
                'name' => 'Security Services',
                'description' => 'Guarding and patrol',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(ServiceCategory::where('name', 'Security Services')->exists())->toBeTrue();
    });

    it('rejects a name that duplicates an existing category', function () {
        ServiceCategory::factory()->create(['name' => 'Security Services']);

        Livewire::test(CreateServiceCategory::class)
            ->fillForm([
                'name' => 'Security Services',
                'active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });
});

describe('deletion', function () {
    it('never authorizes deleting a category, even for the acting user', function () {
        $category = ServiceCategory::factory()->create();

        expect(ServiceCategoryResource::canDelete($category))->toBeFalse();
        expect(ServiceCategoryResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $category = ServiceCategory::factory()->create();

        Livewire::test(EditServiceCategory::class, ['record' => $category->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});
