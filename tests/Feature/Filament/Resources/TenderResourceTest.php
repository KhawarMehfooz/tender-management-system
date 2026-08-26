<?php

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
use Filament\Actions\Testing\TestAction;
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

describe('view page', function () {
    it('shows the status history for a tender with recorded changes', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::INTAKE]);
        $tender->changeStatusTo(TenderStatus::REVIEW, User::factory()->create(), 'Fits our profile');

        Livewire::test(ViewTender::class, ['record' => $tender->getRouteKey()])
            ->assertSee('Fits our profile');
    });
});
