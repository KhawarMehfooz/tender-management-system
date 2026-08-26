<?php

use App\Enums\Right;
use App\Enums\TenderStatus;
use App\Filament\Resources\Tenders\Pages\CreateTender;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ListTenders;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\Source;
use App\Models\Tender;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('creation', function () {
    it('creates a tender through the wizard with valid data', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Guarding services for city hall',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Tender::where('title', 'Guarding services for city hall')->exists())->toBeTrue();

        $tender = Tender::where('title', 'Guarding services for city hall')->first();
        expect($tender->internal_id)->toBe('SEC-'.now()->format('Y').'-0001');
    });

    it('rejects a missing required field', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => null,
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    });
});

describe('deletion', function () {
    it('never authorizes deleting a tender, even for the acting user', function () {
        $tender = Tender::factory()->create();

        expect(TenderResource::canDelete($tender))->toBeFalse();
        expect(TenderResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $tender = Tender::factory()->create();

        Livewire::test(EditTender::class, ['record' => $tender->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});

describe('status change action', function () {
    it('moves the tender to the chosen status and logs who changed it', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('changeStatus')->table($tender), [
                'status' => TenderStatus::REVIEW->value,
                'reason' => 'Fits our profile',
            ])
            ->assertHasNoFormErrors();

        $tender->refresh();
        expect($tender->status)->toBe(TenderStatus::REVIEW);
        expect($tender->statusChanges()->first()->changed_by)->toBe($user->id);
    });

    it('only offers the currently valid next statuses', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('changeStatus')->table($tender), [
                'status' => TenderStatus::DECISION->value,
            ])
            ->assertHasFormErrors(['status']);
    });

    it('hides the action once the tender is in a terminal status', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);

        Livewire::test(ListTenders::class)
            ->assertActionHidden(TestAction::make('changeStatus')->table($tender));
    });
});

describe('field-level rights: estimated contract volume', function () {
    it('hides the price fields on the create form from a user without the see-prices right', function () {
        Livewire::test(CreateTender::class)
            ->assertFormFieldIsHidden('estimated_contract_volume')
            ->assertFormFieldIsHidden('estimated_contract_volume_unknown');
    });

    it('shows the price fields on the create form to a user with the see-prices right', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);

        Livewire::test(CreateTender::class)
            ->assertFormFieldIsVisible('estimated_contract_volume')
            ->assertFormFieldIsVisible('estimated_contract_volume_unknown');
    });

    it('strips a smuggled price value server-side when creating without the see-prices right', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Guarding services for the harbour',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
                'estimated_contract_volume' => 250000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tender = Tender::where('title', 'Guarding services for the harbour')->firstOrFail();
        expect($tender->estimated_contract_volume)->toBeNull();
    });

    it('strips a smuggled price value server-side when editing without the see-prices right', function () {
        $tender = Tender::factory()->create(['estimated_contract_volume' => null]);

        Livewire::test(EditTender::class, ['record' => $tender->getRouteKey()])
            ->fillForm(['estimated_contract_volume' => 999999])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($tender->refresh()->estimated_contract_volume)->toBeNull();
    });

    it('hides the price entry on the view page from a user without the see-prices right', function () {
        $tender = Tender::factory()->create(['estimated_contract_volume' => 42000]);

        Livewire::test(ViewTender::class, ['record' => $tender->getRouteKey()])
            ->assertDontSee('42,000.00');
    });

    it('shows the price entry on the view page to a user with the see-prices right', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);
        $tender = Tender::factory()->create(['estimated_contract_volume' => 42000]);

        Livewire::test(ViewTender::class, ['record' => $tender->getRouteKey()])
            ->assertSee('42,000.00');
    });
});

describe('view page', function () {
    it('shows the status history for a tender with recorded changes', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $tender->changeStatusTo(TenderStatus::REVIEW, User::factory()->create(), 'Fits our profile');

        Livewire::test(ViewTender::class, ['record' => $tender->getRouteKey()])
            ->assertSee('Fits our profile');
    });
});

describe('category-scoped views', function () {
    it('shows a management user (no assigned category) tenders from every category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tenderA = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $tenderB = Tender::factory()->create(['service_category_id' => $categoryB->id]);

        Livewire::test(ListTenders::class)
            ->assertCanSeeTableRecords([$tenderA, $tenderB]);
    });

    it('scopes a category-assigned user to only their own category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tenderA = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $tenderB = Tender::factory()->create(['service_category_id' => $categoryB->id]);

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryA->id]));

        Livewire::test(ListTenders::class)
            ->assertCanSeeTableRecords([$tenderA])
            ->assertCanNotSeeTableRecords([$tenderB]);
    });

    it('blocks a category-scoped user from viewing a tender outside their category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $foreignTender = Tender::factory()->create(['service_category_id' => $categoryB->id]);

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryA->id]));

        expect(fn () => Livewire::test(ViewTender::class, ['record' => $foreignTender->getRouteKey()]))
            ->toThrow(ModelNotFoundException::class);
    });

    it('blocks a category-scoped user from editing a tender outside their category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $foreignTender = Tender::factory()->create(['service_category_id' => $categoryB->id]);

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryA->id]));

        expect(fn () => Livewire::test(EditTender::class, ['record' => $foreignTender->getRouteKey()]))
            ->toThrow(ModelNotFoundException::class);
    });

    it('forces a new tender into the scoped user\'s own category regardless of what is submitted', function () {
        $ownCategory = ServiceCategory::factory()->create(['code' => 'OWN']);
        $otherCategory = ServiceCategory::factory()->create(['code' => 'OTH']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();

        $this->actingAs(User::factory()->create(['service_category_id' => $ownCategory->id]));

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Cleaning services for the town hall',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $otherCategory->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tender = Tender::where('title', 'Cleaning services for the town hall')->firstOrFail();
        expect($tender->service_category_id)->toBe($ownCategory->id);
    });
});
