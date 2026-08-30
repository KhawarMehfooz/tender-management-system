<?php

use App\Enums\BidDecision;
use App\Enums\CalculationApprovalStep;
use App\Enums\CalculationModel;
use App\Enums\CostDriverFieldType;
use App\Enums\DeadlineType;
use App\Enums\DocumentCategory;
use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Filament\Resources\Tenders\Pages\CreateTender;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ListTenders;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\BidDecisionRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CalculationsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\ServiceCategoryCostDriverField;
use App\Models\Source;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\TenderCalculation;
use App\Models\TenderDeadline;
use App\Models\TenderDocument;
use App\Models\TenderHardDeletion;
use App\Models\TenderParticipationScore;
use App\Models\TenderTeamMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    it('rejects moving to submission while the calculation approval chain is incomplete', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::QUALITY]);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('changeStatus')->table($tender), [
                'status' => TenderStatus::SUBMISSION->value,
            ])
            ->assertHasFormErrors(['status']);

        expect($tender->fresh()->status)->toBe(TenderStatus::QUALITY);
    });

    it('allows moving to submission once the current calculation is fully approved', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::QUALITY]);
        fullyApprovedCalculationFor($tender);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('changeStatus')->table($tender), [
                'status' => TenderStatus::SUBMISSION->value,
            ])
            ->assertHasNoFormErrors();

        expect($tender->fresh()->status)->toBe(TenderStatus::SUBMISSION);
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

describe('archive action', function () {
    it('archives an active tender', function () {
        $tender = Tender::factory()->create(['is_archived' => false]);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('archive')->table($tender))
            ->assertHasNoFormErrors();

        expect($tender->fresh()->is_archived)->toBeTrue();
    });

    it('hides the archive action once already archived, and offers unarchive instead', function () {
        $tender = Tender::factory()->create();
        $tender->archive(User::factory()->create());

        Livewire::test(ListTenders::class)
            ->assertActionHidden(TestAction::make('archive')->table($tender))
            ->callAction(TestAction::make('unarchive')->table($tender))
            ->assertHasNoFormErrors();

        expect($tender->fresh()->is_archived)->toBeFalse();
    });
});

describe('mark invalid action', function () {
    it('flags a tender invalid with the given reason', function () {
        $tender = Tender::factory()->create();

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('markInvalid')->table($tender), [
                'reason' => 'Duplicate of another tender',
            ])
            ->assertHasNoFormErrors();

        $tender->refresh();
        expect($tender->isInvalid())->toBeTrue();
        expect($tender->invalidity_reason)->toBe('Duplicate of another tender');
    });

    it('requires a reason', function () {
        $tender = Tender::factory()->create();

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('markInvalid')->table($tender), ['reason' => ''])
            ->assertHasFormErrors(['reason' => 'required']);
    });

    it('hides mark-invalid once flagged, and offers clearing the flag instead', function () {
        $tender = Tender::factory()->create();
        $tender->markInvalid(User::factory()->create(), 'Duplicate');

        Livewire::test(ListTenders::class)
            ->assertActionHidden(TestAction::make('markInvalid')->table($tender))
            ->callAction(TestAction::make('clearInvalidFlag')->table($tender))
            ->assertHasNoFormErrors();

        expect($tender->fresh()->isInvalid())->toBeFalse();
    });
});

describe('hard delete action', function () {
    it('hides the hard-delete action from a non-super-admin', function () {
        $tender = Tender::factory()->create();

        Livewire::test(ListTenders::class)
            ->assertActionHidden(TestAction::make('hardDelete')->table($tender));
    });

    it('offers the hard-delete action to a super admin', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN));

        Livewire::test(ListTenders::class)
            ->assertActionVisible(TestAction::make('hardDelete')->table($tender));
    });

    it('permanently deletes the tender and logs who/when/why as a super admin', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
        $this->actingAs($admin);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('hardDelete')->table($tender), [
                'reason' => 'Confirmed technical junk entry',
            ])
            ->assertHasNoFormErrors();

        expect(Tender::withoutGlobalScopes()->find($tender->id))->toBeNull();

        $log = TenderHardDeletion::query()->where('tender_id', $tender->id)->firstOrFail();
        expect($log->deleted_by)->toBe($admin->id);
        expect($log->reason)->toBe('Confirmed technical junk entry');
    });

    it('requires a reason', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $this->actingAs(tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN));

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('hardDelete')->table($tender), ['reason' => ''])
            ->assertHasFormErrors(['reason' => 'required']);
    });
});

describe('team assignment', function () {
    it('disables the owner and team member fields for a user without team-assignment rights', function () {
        Livewire::test(CreateTender::class)
            ->assertFormFieldIsDisabled('owner_id')
            ->assertFormFieldIsDisabled('teamMembers');
    });

    it('enables the owner and team member fields for a team lead', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        Livewire::test(CreateTender::class)
            ->assertFormFieldIsEnabled('owner_id')
            ->assertFormFieldIsEnabled('teamMembers');
    });

    it('defaults the owner to the creating user when they lack team-assignment rights', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Guarding services for the airport',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tender = Tender::where('title', 'Guarding services for the airport')->firstOrFail();
        expect($tender->owner_id)->toBe($user->id);
    });

    it('lets a team lead set the owner and add team members on create', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Guarding services for the museum',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
                'owner_id' => $owner->id,
                'teamMembers' => [
                    ['user_id' => $member->id, 'functional_role' => TeamRole::QUALITY_CONTROL->value],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tender = Tender::where('title', 'Guarding services for the museum')->firstOrFail();
        expect($tender->owner_id)->toBe($owner->id);

        $teamMember = TenderTeamMember::where('tender_id', $tender->id)->firstOrFail();
        expect($teamMember->user_id)->toBe($member->id);
        expect($teamMember->functional_role)->toBe(TeamRole::QUALITY_CONTROL);
    });

    it('strips a smuggled owner value server-side when creating without team-assignment rights', function () {
        $category = ServiceCategory::factory()->create(['code' => 'SEC']);
        $sector = Sector::factory()->create();
        $procedure = ProcurementProcedure::factory()->create();
        $source = Source::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateTender::class)
            ->fillForm([
                'title' => 'Guarding services for the depot',
                'contracting_authority' => 'City of Example',
                'service_category_id' => $category->id,
                'sector_id' => $sector->id,
                'procurement_procedure_id' => $procedure->id,
                'source_id' => $source->id,
                'submission_deadline' => now()->addWeeks(2),
                'owner_id' => $otherUser->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tender = Tender::where('title', 'Guarding services for the depot')->firstOrFail();
        expect($tender->owner_id)->toBe($user->id);
    });

    it('keeps the existing owner when a user without team-assignment rights tries to change it on edit', function () {
        $originalOwner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $originalOwner->id]);
        $otherUser = User::factory()->create();

        Livewire::test(EditTender::class, ['record' => $tender->getRouteKey()])
            ->fillForm(['owner_id' => $otherUser->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($tender->refresh()->owner_id)->toBe($originalOwner->id);
    });

    it('lets a team lead reassign the owner on edit', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $tender = Tender::factory()->create();
        $newOwner = User::factory()->create();

        Livewire::test(EditTender::class, ['record' => $tender->getRouteKey()])
            ->fillForm(['owner_id' => $newOwner->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($tender->refresh()->owner_id)->toBe($newOwner->id);
    });
});

describe('deadlines relation manager', function () {
    it('lists only the tender\'s own deadlines', function () {
        $tender = Tender::factory()->create();
        $deadline = TenderDeadline::factory()->for($tender)->create(['type' => DeadlineType::SITE_VISIT]);
        $foreignDeadline = TenderDeadline::factory()->create();

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$deadline])
            ->assertCanNotSeeTableRecords([$foreignDeadline]);
    });

    it('hides the manage actions for a user without team-assignment rights', function () {
        $tender = Tender::factory()->create();

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets a team lead create a deadline', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $tender = Tender::factory()->create();
        $dueAt = now()->addWeeks(3);

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'type' => DeadlineType::BIDDER_QUESTIONS->value,
                'due_at' => $dueAt,
            ])
            ->assertHasNoTableActionErrors();

        $deadline = TenderDeadline::where('tender_id', $tender->id)
            ->where('type', DeadlineType::BIDDER_QUESTIONS)
            ->firstOrFail();
        expect($deadline->due_at->format('Y-m-d H:i'))->toBe($dueAt->format('Y-m-d H:i'));
    });

    it('rejects bid validity as a selectable type since it is derived automatically', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $tender = Tender::factory()->create();

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'type' => DeadlineType::BID_VALIDITY->value,
                'due_at' => now()->addWeeks(3),
            ])
            ->assertHasTableActionErrors(['type']);
    });

    it('lets a team lead delete a deadline', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $tender = Tender::factory()->create();
        $deadline = TenderDeadline::factory()->for($tender)->create(['type' => DeadlineType::PRESENTATION]);

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('delete', record: $deadline)
            ->assertHasNoTableActionErrors();

        expect(TenderDeadline::find($deadline->id))->toBeNull();
    });
});

describe('documents relation manager', function () {
    it('lists only the tender\'s own documents', function () {
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $foreignDocument = TenderDocument::factory()->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$document])
            ->assertCanNotSeeTableRecords([$foreignDocument]);
    });

    it('hides the new document action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner upload a new document with its first version', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'title' => 'Tender specification',
                'category' => DocumentCategory::TENDER_DOCUMENTS->value,
                'file' => UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $document = $tender->documents()->firstOrFail();
        expect($document->created_by)->toBe($owner->id);
        expect($document->currentVersion->version_number)->toBe(1);
        expect($document->currentVersion->original_filename)->toBe('spec.pdf');
        expect(Storage::disk('local')->exists($document->currentVersion->file_path))->toBeTrue();
    });

    it('lets a tender manager upload a document even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create');
    });

    it('rejects the calculation category as a selectable option for a user without the see-prices right', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'title' => 'Pricing sheet',
                'category' => DocumentCategory::CALCULATION->value,
                'file' => UploadedFile::fake()->create('pricing.pdf', 100, 'application/pdf'),
            ])
            ->assertHasTableActionErrors(['category']);
    });

    it('hides a calculation document from a user without the see-prices right', function () {
        $tender = Tender::factory()->create();
        $calculationDocument = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::CALCULATION]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanNotSeeTableRecords([$calculationDocument]);
    });

    it('shows a calculation document to a user with the see-prices right', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);
        $tender = Tender::factory()->create();
        $calculationDocument = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::CALCULATION]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$calculationDocument]);
    });

    it('lets a linked team member add a new version, incrementing the version number', function () {
        Storage::fake('local');
        $member = User::factory()->create();
        $tender = Tender::factory()->create();
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'user_id' => $member->id,
            'functional_role' => TeamRole::EVIDENCE_DOCUMENTS,
        ]);
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/original.pdf',
            'original_filename' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        $this->actingAs($member);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('addVersion', $document)
            ->callTableAction('addVersion', record: $document, data: [
                'file' => UploadedFile::fake()->create('revised.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        expect($document->refresh()->currentVersion->version_number)->toBe(2);
        expect($document->currentVersion->original_filename)->toBe('revised.pdf');
    });

    it('hides the new version action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('addVersion', $document);
    });

    it('hides the new version action once the document is locked', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $document->lock($owner);
        $this->actingAs($owner);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('addVersion', $document);
    });

    it('lets the document creator delete it', function () {
        Storage::fake('local');
        $creator = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $creator->id]);
        $document = TenderDocument::factory()->for($tender)->create([
            'category' => DocumentCategory::TENDER_DOCUMENTS,
            'created_by' => $creator->id,
        ]);
        Storage::disk('local')->put('tender-documents/original.pdf', 'contents');
        $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/original.pdf',
            'original_filename' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $creator->id,
        ]);
        $this->actingAs($creator);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('delete', $document)
            ->callTableAction('delete', record: $document)
            ->assertHasNoTableActionErrors();

        expect(TenderDocument::find($document->id))->toBeNull();
        expect(Storage::disk('local')->exists('tender-documents/original.pdf'))->toBeFalse();
    });

    it('hides delete from a different linked user who did not create the document', function () {
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'user_id' => $otherMember->id,
            'functional_role' => TeamRole::EVIDENCE_DOCUMENTS,
        ]);
        $document = TenderDocument::factory()->for($tender)->create([
            'category' => DocumentCategory::TENDER_DOCUMENTS,
            'created_by' => $owner->id,
        ]);
        $this->actingAs($otherMember);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('delete', $document);
    });

    it('lets a tender manager delete any document', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('delete', $document);
    });

    it('hides delete once the document is locked, even for the creator', function () {
        $creator = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $creator->id]);
        $document = TenderDocument::factory()->for($tender)->create([
            'category' => DocumentCategory::TENDER_DOCUMENTS,
            'created_by' => $creator->id,
        ]);
        $document->lock($creator);
        $this->actingAs($creator);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('delete', $document);
    });

    it('hides the download action when the document has no version yet', function () {
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertTableActionHidden('download', $document);
    });

    it('shows the download action once a version exists', function () {
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/original.pdf',
            'original_filename' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertTableActionVisible('download', $document);
    });
});

describe('document download', function () {
    it('streams the file for a user within the document\'s tender category', function () {
        Storage::fake('local');
        $category = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/spec.pdf',
            'original_filename' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $category->id]))
            ->get($version->downloadUrl())
            ->assertOk();
    });

    it('returns 404 for a user outside the document\'s tender category', function () {
        Storage::fake('local');
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/spec.pdf',
            'original_filename' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryB->id]))
            ->get($version->downloadUrl())
            ->assertNotFound();
    });

    it('returns 403 for a calculation document downloaded by a user without the see-prices right', function () {
        Storage::fake('local');
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::CALCULATION]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/pricing.pdf',
            'original_filename' => 'pricing.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get($version->downloadUrl())
            ->assertForbidden();
    });

    it('allows a calculation document download for a user with the see-prices right', function () {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(Right::SEE_PRICES->value);
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->for($tender)->create(['category' => DocumentCategory::CALCULATION]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/pricing.pdf',
            'original_filename' => 'pricing.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');

        $this->actingAs($user)
            ->get($version->downloadUrl())
            ->assertOk();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $document = TenderDocument::factory()->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/spec.pdf',
            'original_filename' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get(route('tender-documents.download', $version))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $document = TenderDocument::factory()->create(['category' => DocumentCategory::TENDER_DOCUMENTS]);
        $version = $document->versions()->create([
            'version_number' => 1,
            'file_path' => 'tender-documents/spec.pdf',
            'original_filename' => 'spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $document->created_by,
        ]);
        Storage::disk('local')->put($version->file_path, 'contents');
        $expiredUrl = $version->downloadUrl();

        $this->travel(6)->minutes();

        $this->actingAs(User::factory()->create())
            ->get($expiredUrl)
            ->assertForbidden();
    });
});

/**
 * A service category configured with the deployment-hours model and its required cost-driver
 * fields, matching DeploymentHoursCalculationEngine's fixture in CalculationEngineTest.
 */
function deploymentHoursServiceCategory(): ServiceCategory
{
    $category = ServiceCategory::factory()->create(['calculation_model' => CalculationModel::DEPLOYMENT_HOURS]);

    foreach ([
        'hours' => CostDriverFieldType::NUMBER,
        'wage_rate' => CostDriverFieldType::DECIMAL,
        'supplements_pct' => CostDriverFieldType::DECIMAL,
        'social_costs_pct' => CostDriverFieldType::DECIMAL,
        'target_margin_pct' => CostDriverFieldType::DECIMAL,
        'min_margin_pct' => CostDriverFieldType::DECIMAL,
        'risk_surcharge_pct' => CostDriverFieldType::DECIMAL,
    ] as $fieldKey => $type) {
        ServiceCategoryCostDriverField::factory()->create([
            'service_category_id' => $category->id,
            'field_key' => $fieldKey,
            'type' => $type,
        ]);
    }

    return $category;
}

describe('calculations relation manager', function () {
    it('lists only the tender\'s own calculations', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->for($tender)->create();
        $foreignCalculation = TenderCalculation::factory()->create();

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$calculation])
            ->assertCanNotSeeTableRecords([$foreignCalculation]);
    });

    it('hides the new calculation action from a user without the see-prices right', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'service_category_id' => deploymentHoursServiceCategory()->id]);
        $this->actingAs($owner);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('hides the new calculation action when the service category has no calculation model configured', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->givePermissionTo(Right::SEE_PRICES->value);
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner with see-prices create a calculation and computes its outputs', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->givePermissionTo(Right::SEE_PRICES->value);
        $category = deploymentHoursServiceCategory();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'service_category_id' => $category->id]);
        $this->actingAs($owner);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'input_values' => [
                    'hours' => 100,
                    'wage_rate' => 20,
                    'supplements_pct' => 0.1,
                    'social_costs_pct' => 0.2,
                    'target_margin_pct' => 0.15,
                    'min_margin_pct' => 0.1,
                    'risk_surcharge_pct' => 0.05,
                ],
            ])
            ->assertHasNoTableActionErrors();

        $calculation = $tender->calculations()->firstOrFail();
        expect($calculation->version_number)->toBe(1);
        expect($calculation->created_by)->toBe($owner->id);
        expect((float) $calculation->bid_price)->toEqualWithDelta(3187.8, 0.01);
    });

    it('lets a manager duplicate a calculation, pre-filling and incrementing the version number', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);
        $category = deploymentHoursServiceCategory();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $inputs = [
            'hours' => 100,
            'wage_rate' => 20,
            'supplements_pct' => 0.1,
            'social_costs_pct' => 0.2,
            'target_margin_pct' => 0.15,
            'min_margin_pct' => 0.1,
            'risk_surcharge_pct' => 0.05,
        ];
        $calculation = TenderCalculation::factory()->for($tender)->create([
            'version_number' => 1,
            'input_values' => $inputs,
        ]);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->mountTableAction('duplicate', $calculation)
            ->assertTableActionDataSet(['input_values' => $inputs])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        expect($tender->calculations()->count())->toBe(2);
        $duplicate = $tender->calculations()->where('version_number', 2)->firstOrFail();
        expect($duplicate->input_values)->toEqual($inputs);
        expect($duplicate->bid_price)->not->toBeNull();
    });

    it('lets the calculation-role team member approve the chain\'s first step', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $member = User::factory()->create();
        $tender = Tender::factory()->create();
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'user_id' => $member->id,
            'functional_role' => TeamRole::CALCULATION,
        ]);
        $calculation = TenderCalculation::factory()->for($tender)->create();
        $this->actingAs($member);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('approveNextStep', $calculation)
            ->callTableAction('approveNextStep', record: $calculation, data: ['comment' => 'Looks good'])
            ->assertHasNoTableActionErrors();

        $approval = $calculation->approvals()->where('step', CalculationApprovalStep::CALCULATION_CHECKED)->firstOrFail();
        expect($approval->approved_by)->toBe($member->id);
        expect($approval->comment)->toBe('Looks good');
    });

    it('hides the approve action from a user without the matching team role', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->for($tender)->create();

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('approveNextStep', $calculation);
    });

    it('hides the approve action once the chain is fully approved', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(Right::EXECUTE_FINAL_SUBMISSION->value);
        $tender = Tender::factory()->create();
        $calculation = fullyApprovedCalculationFor($tender);
        $this->actingAs($user);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('approveNextStep', $calculation);
    });

    it('hides financial columns from a user without the see-prices right', function () {
        $tender = Tender::factory()->create();
        TenderCalculation::factory()->for($tender)->create(['bid_price' => 100]);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertTableColumnHidden('bid_price')
            ->assertTableColumnVisible('version_number');
    });

    it('shows financial columns to a user with the see-prices right', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);
        $tender = Tender::factory()->create();
        TenderCalculation::factory()->for($tender)->create(['bid_price' => 100]);

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertTableColumnVisible('bid_price');
    });

    it('shows the formula reference for the tender\'s calculation model in the view action', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::SEE_PRICES->value);
        $category = deploymentHoursServiceCategory();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $calculation = TenderCalculation::factory()->for($tender)->create();

        Livewire::test(CalculationsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->mountTableAction('view', $calculation)
            ->assertMountedActionModalSee('cost_per_hour = wage_rate');
    });
});

describe('bid decision relation manager', function () {
    it('lists only the tender\'s own bid decisions', function () {
        $tender = Tender::factory()->create();
        $decision = TenderBidDecision::factory()->for($tender)->create();
        $foreignDecision = TenderBidDecision::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$decision])
            ->assertCanNotSeeTableRecords([$foreignDecision]);
    });

    it('hides both actions from a user without the make-bid-decision right', function () {
        $tender = Tender::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('editScoreInputs')
            ->assertTableActionHidden('recordDecision');
    });

    it('shows the incomplete-ratings summary when no ratings have been entered', function () {
        $tender = Tender::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertSee('Incomplete — 7 of 7 ratings missing');
    });

    it('shows the computed score once all ratings are entered', function () {
        $tender = Tender::factory()->create(['estimated_contract_volume' => 2_000_000]);
        TenderCalculation::factory()->for($tender)->create(['version_number' => 1, 'actual_margin' => 25]);
        TenderParticipationScore::factory()->rated(5)->create(['tender_id' => $tender->id]);

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertSee('96 / 100');
    });

    it('lets a user with the right edit the score inputs', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::MAKE_BID_DECISION->value);
        $tender = Tender::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('editScoreInputs')
            ->callTableAction('editScoreInputs', data: [
                'distance_rating' => 4,
                'staffing_requirement_rating' => 4,
                'wage_qualification_rating' => 4,
                'reference_position_rating' => 4,
                'competitive_intensity_rating' => 4,
                'contractual_penalties_rating' => 4,
                'strategic_value_rating' => 4,
            ])
            ->assertHasNoTableActionErrors();

        $score = $tender->participationScore()->firstOrFail();
        expect($score->distance_rating)->toBe(4);
        expect($score->strategic_value_rating)->toBe(4);
    });

    it('lets a user with the right record a BID decision without a reason', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::MAKE_BID_DECISION->value);
        $tender = Tender::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('recordDecision')
            ->callTableAction('recordDecision', data: ['decision' => BidDecision::BID->value])
            ->assertHasNoTableActionErrors();

        $decision = $tender->currentBidDecision()->firstOrFail();
        expect($decision->decision)->toBe(BidDecision::BID);
        expect($decision->decided_by)->toBe(auth()->id());
    });

    it('requires a reason when recording a NO_BID decision', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::MAKE_BID_DECISION->value);
        $tender = Tender::factory()->create();

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('recordDecision', data: ['decision' => BidDecision::NO_BID->value])
            ->assertHasTableActionErrors(['reason' => 'required']);
    });

    it('records a NO_BID decision with a reason, snapshotting the current score', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::MAKE_BID_DECISION->value);
        $tender = Tender::factory()->create(['estimated_contract_volume' => 2_000_000]);
        TenderCalculation::factory()->for($tender)->create(['version_number' => 1, 'actual_margin' => 25]);
        TenderParticipationScore::factory()->rated(5)->create(['tender_id' => $tender->id]);

        Livewire::test(BidDecisionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('recordDecision', data: [
                'decision' => BidDecision::NO_BID->value,
                'reason' => 'Margin too thin.',
            ])
            ->assertHasNoTableActionErrors();

        $decision = $tender->currentBidDecision()->firstOrFail();
        expect($decision->decision)->toBe(BidDecision::NO_BID);
        expect($decision->reason)->toBe('Margin too thin.');
        expect($decision->score)->toBe(96);
    });
});
