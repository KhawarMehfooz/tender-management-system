<?php

use App\Enums\CostDriverFieldType;
use App\Filament\Resources\ServiceCategories\Pages\CreateServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Resources\ServiceCategories\Pages\ViewServiceCategory;
use App\Filament\Resources\ServiceCategories\RelationManagers\CostDriverFieldsRelationManager;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Models\ServiceCategoryCostDriverField;
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

describe('cost driver fields relation manager', function () {
    it('lists a category\'s cost driver fields', function () {
        $category = ServiceCategory::factory()->create();
        $field = ServiceCategoryCostDriverField::factory()->create(['service_category_id' => $category->id]);
        $foreignField = ServiceCategoryCostDriverField::factory()->create();

        Livewire::test(CostDriverFieldsRelationManager::class, ['ownerRecord' => $category, 'pageClass' => ViewServiceCategory::class])
            ->assertCanSeeTableRecords([$field])
            ->assertCanNotSeeTableRecords([$foreignField]);
    });

    it('creates a cost driver field', function () {
        $category = ServiceCategory::factory()->create();

        Livewire::test(CostDriverFieldsRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditServiceCategory::class])
            ->callTableAction('create', data: [
                'field_key' => 'deployment_hours',
                'label' => 'Deployment hours',
                'type' => CostDriverFieldType::NUMBER->value,
                'unit' => 'h',
                'required' => true,
            ])
            ->assertHasNoTableActionErrors();

        expect(ServiceCategoryCostDriverField::where('service_category_id', $category->id)
            ->where('field_key', 'deployment_hours')
            ->exists())->toBeTrue();
    });

    it('rejects a duplicate field_key within the same category', function () {
        $category = ServiceCategory::factory()->create();
        ServiceCategoryCostDriverField::factory()->create(['service_category_id' => $category->id, 'field_key' => 'deployment_hours']);

        Livewire::test(CostDriverFieldsRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditServiceCategory::class])
            ->callTableAction('create', data: [
                'field_key' => 'deployment_hours',
                'label' => 'Deployment hours',
                'type' => CostDriverFieldType::NUMBER->value,
                'required' => true,
            ])
            ->assertHasTableActionErrors(['field_key' => 'unique']);
    });
});
