<?php

use App\Enums\BidDecision;
use App\Enums\CalculationApprovalStep;
use App\Enums\CalculationModel;
use App\Enums\CommunicationType;
use App\Enums\CostDriverFieldType;
use App\Enums\DeadlineType;
use App\Enums\DocumentCategory;
use App\Enums\DocumentRequestStatus;
use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Enums\WinLossReason;
use App\Filament\Resources\Tenders\Pages\CreateTender;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ListTenders;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\BidDecisionRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CalculationsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\CommunicationRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ConceptBlocksRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DocumentRequestsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\FollowUpRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\LessonsLearnedRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ReferencesRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\ResultRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\SiteVisitsRelationManager;
use App\Filament\Resources\Tenders\RelationManagers\SubmissionRelationManager;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\Certificate;
use App\Models\ConceptBlock;
use App\Models\ConceptBlockVersion;
use App\Models\ProcurementProcedure;
use App\Models\Reference;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\ServiceCategoryCostDriverField;
use App\Models\Source;
use App\Models\Tender;
use App\Models\TenderBidDecision;
use App\Models\TenderCalculation;
use App\Models\TenderCommunication;
use App\Models\TenderDeadline;
use App\Models\TenderDocument;
use App\Models\TenderDocumentRequest;
use App\Models\TenderDocumentRequestFile;
use App\Models\TenderFollowUp;
use App\Models\TenderHardDeletion;
use App\Models\TenderLessonsLearned;
use App\Models\TenderParticipationScore;
use App\Models\TenderResult;
use App\Models\TenderSiteVisit;
use App\Models\TenderSiteVisitPhoto;
use App\Models\TenderSubmission;
use App\Models\TenderSubmissionFile;
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

describe('communication relation manager', function () {
    it('lists only the tender\'s own communications', function () {
        $tender = Tender::factory()->create();
        $entry = TenderCommunication::factory()->for($tender)->create();
        $foreignEntry = TenderCommunication::factory()->create();

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$entry])
            ->assertCanNotSeeTableRecords([$foreignEntry]);
    });

    it('hides the create action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner log a communication entry, stamping the acting user as logged_by', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'type' => CommunicationType::EMAIL->value,
                'subject' => 'Clarification on delivery deadline',
                'content' => 'Asked the client to confirm the delivery deadline.',
                'contact_person' => 'Jane Doe',
                'occurred_at' => now(),
            ])
            ->assertHasNoTableActionErrors();

        $entry = $tender->communications()->firstOrFail();
        expect($entry->logged_by)->toBe($owner->id);
        expect($entry->type)->toBe(CommunicationType::EMAIL);
    });

    it('lets a tender manager log a communication entry even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create');
    });

    it('lets the entry\'s own author edit it', function () {
        $author = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $author->id]);
        $entry = TenderCommunication::factory()->for($tender)->create(['logged_by' => $author->id]);
        $this->actingAs($author);

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $entry);
    });

    it('hides edit from a different linked user who did not log the entry', function () {
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'user_id' => $otherMember->id,
            'functional_role' => TeamRole::EVIDENCE_DOCUMENTS,
        ]);
        $entry = TenderCommunication::factory()->for($tender)->create(['logged_by' => $owner->id]);
        $this->actingAs($otherMember);

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $entry);
    });

    it('lets a tender manager edit any entry', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();
        $entry = TenderCommunication::factory()->for($tender)->create();

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $entry);
    });

    it('has no delete action, since the log is append-only', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $entry = TenderCommunication::factory()->for($tender)->create(['logged_by' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CommunicationRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionDoesNotExist('delete', record: $entry);
    });
});

describe('site visits relation manager', function () {
    it('lists only the tender\'s own site visits', function () {
        $tender = Tender::factory()->create();
        $visit = TenderSiteVisit::factory()->for($tender)->create();
        $foreignVisit = TenderSiteVisit::factory()->create();

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$visit])
            ->assertCanNotSeeTableRecords([$foreignVisit]);
    });

    it('hides the create action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner record a site visit, stamping the acting user as created_by', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'visit_date' => now()->toDateString(),
                'attendees' => 'Jane Doe, John Smith',
            ])
            ->assertHasNoTableActionErrors();

        $visit = $tender->siteVisits()->firstOrFail();
        expect($visit->created_by)->toBe($owner->id);
    });

    it('lets a tender manager record a site visit even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create');
    });

    it('lets a linked user upload a photo to a site visit', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $visit = TenderSiteVisit::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('uploadPhoto', $visit)
            ->callTableAction('uploadPhoto', record: $visit, data: [
                'file' => UploadedFile::fake()->image('site.jpg'),
            ])
            ->assertHasNoTableActionErrors();

        $photo = $visit->photos()->firstOrFail();
        expect($photo->uploaded_by)->toBe($owner->id);
        expect($photo->original_filename)->toBe('site.jpg');
        expect(Storage::disk('local')->exists($photo->file_path))->toBeTrue();
    });

    it('hides the upload photo action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $visit = TenderSiteVisit::factory()->for($tender)->create();

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('uploadPhoto', $visit);
    });

    it('lets the visit creator delete it, removing its photo files', function () {
        Storage::fake('local');
        $creator = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $creator->id]);
        $visit = TenderSiteVisit::factory()->for($tender)->create(['created_by' => $creator->id]);
        Storage::disk('local')->put('tender-site-visit-photos/photo.jpg', 'contents');
        $photo = TenderSiteVisitPhoto::factory()->for($visit, 'siteVisit')->create([
            'file_path' => 'tender-site-visit-photos/photo.jpg',
            'uploaded_by' => $creator->id,
        ]);
        $this->actingAs($creator);

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('delete', $visit)
            ->callTableAction('delete', record: $visit)
            ->assertHasNoTableActionErrors();

        expect(TenderSiteVisit::find($visit->id))->toBeNull();
        expect(TenderSiteVisitPhoto::find($photo->id))->toBeNull();
        expect(Storage::disk('local')->exists('tender-site-visit-photos/photo.jpg'))->toBeFalse();
    });

    it('hides delete from a different linked user who did not create the visit', function () {
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        TenderTeamMember::factory()->create([
            'tender_id' => $tender->id,
            'user_id' => $otherMember->id,
            'functional_role' => TeamRole::EVIDENCE_DOCUMENTS,
        ]);
        $visit = TenderSiteVisit::factory()->for($tender)->create(['created_by' => $owner->id]);
        $this->actingAs($otherMember);

        Livewire::test(SiteVisitsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('delete', $visit);
    });
});

describe('site visit photo download', function () {
    it('streams the file for a user within the visit\'s tender category', function () {
        Storage::fake('local');
        $category = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $visit = TenderSiteVisit::factory()->for($tender)->create();
        $photo = TenderSiteVisitPhoto::factory()->for($visit, 'siteVisit')->create([
            'file_path' => 'tender-site-visit-photos/site.jpg',
        ]);
        Storage::disk('local')->put($photo->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $category->id]))
            ->get($photo->downloadUrl())
            ->assertOk();
    });

    it('returns 404 for a user outside the visit\'s tender category', function () {
        Storage::fake('local');
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $visit = TenderSiteVisit::factory()->for($tender)->create();
        $photo = TenderSiteVisitPhoto::factory()->for($visit, 'siteVisit')->create([
            'file_path' => 'tender-site-visit-photos/site.jpg',
        ]);
        Storage::disk('local')->put($photo->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryB->id]))
            ->get($photo->downloadUrl())
            ->assertNotFound();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $photo = TenderSiteVisitPhoto::factory()->create(['file_path' => 'tender-site-visit-photos/site.jpg']);
        Storage::disk('local')->put($photo->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get(route('tender-site-visit-photos.download', $photo))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $photo = TenderSiteVisitPhoto::factory()->create(['file_path' => 'tender-site-visit-photos/site.jpg']);
        Storage::disk('local')->put($photo->file_path, 'contents');
        $expiredUrl = $photo->downloadUrl();

        $this->travel(6)->minutes();

        $this->actingAs(User::factory()->create())
            ->get($expiredUrl)
            ->assertForbidden();
    });
});

describe('submission relation manager', function () {
    it('hides the create action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner record the submission, stamping created_by and the receipt-confirmed timestamp', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $employee = User::factory()->create();
        $this->actingAs($owner);

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'submission_date' => now()->toDateString(),
                'submission_time' => '14:30',
                'responsible_employee_id' => $employee->id,
                'portal' => 'e-Vergabe',
                'transmission_route' => 'Electronic portal upload',
                'receipt_confirmed' => true,
            ])
            ->assertHasNoTableActionErrors();

        $submission = $tender->submission()->firstOrFail();
        expect($submission->created_by)->toBe($owner->id);
        expect($submission->receipt_confirmed)->toBeTrue();
        expect($submission->receipt_confirmed_at)->not->toBeNull();
    });

    it('hides the create action once a submission already exists', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        TenderSubmission::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets a tender manager edit the submission even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();
        $submission = TenderSubmission::factory()->for($tender)->create();

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $submission);
    });

    it('hides edit from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $submission = TenderSubmission::factory()->for($tender)->create();

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $submission);
    });

    it('lets a linked user upload a submission file', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $submission = TenderSubmission::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(SubmissionRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('uploadFile', $submission)
            ->callTableAction('uploadFile', record: $submission, data: [
                'file' => UploadedFile::fake()->create('bid.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $file = $submission->files()->firstOrFail();
        expect($file->uploaded_by)->toBe($owner->id);
        expect($file->original_filename)->toBe('bid.pdf');
        expect(Storage::disk('local')->exists($file->file_path))->toBeTrue();
    });
});

describe('follow-up relation manager', function () {
    it('hides the create action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(FollowUpRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner record the follow-up, stamping created_by', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(FollowUpRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'presentation_scheduled_at' => now()->addWeek()->toDateTimeString(),
                'bid_validity_until' => now()->addMonths(3)->toDateString(),
                'expected_result_date' => now()->addMonths(2)->toDateString(),
                'presentation_notes' => 'Presentation notes',
                'negotiation_notes' => 'Negotiation notes',
                'expected_result_notes' => 'Expected result notes',
            ])
            ->assertHasNoTableActionErrors();

        $followUp = $tender->followUp()->firstOrFail();
        expect($followUp->created_by)->toBe($owner->id);
        expect($followUp->presentation_notes)->toBe('Presentation notes');
    });

    it('hides the create action once a follow-up record already exists', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        TenderFollowUp::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(FollowUpRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets a tender manager edit the follow-up even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();
        $followUp = TenderFollowUp::factory()->for($tender)->create();

        Livewire::test(FollowUpRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $followUp);
    });

    it('hides edit from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $followUp = TenderFollowUp::factory()->for($tender)->create();

        Livewire::test(FollowUpRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $followUp);
    });
});

describe('result relation manager', function () {
    it('hides the create action for a non-terminal tender even for a manager', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create(['status' => TenderStatus::QUALITY]);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('hides the create action from a user with no link to a terminal tender', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner record the result on a terminal tender, stamping created_by', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::LOST]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner->givePermissionTo(Right::SEE_PRICES->value);
        $this->actingAs($owner);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'winner' => 'Acme Facility Services GmbH',
                'our_rank' => 2,
                'award_date' => now()->toDateString(),
                'win_loss_reasons' => [WinLossReason::PRICE->value, WinLossReason::STAFFING->value],
            ])
            ->assertHasNoTableActionErrors();

        $result = $tender->result()->firstOrFail();
        expect($result->created_by)->toBe($owner->id);
        expect($result->winner)->toBe('Acme Facility Services GmbH');
        expect($result->win_loss_reasons)->toBe([WinLossReason::PRICE->value, WinLossReason::STAFFING->value]);
    });

    it('hides the create action once a result already exists', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        TenderResult::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('hides the price fields and columns from a user without the see-prices right', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        TenderResult::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableColumnHidden('winning_price')
            ->assertTableColumnHidden('our_price')
            ->assertTableColumnHidden('price_gap');
    });

    it('computes the price gap server-side from winning and our price, and leaves it null when a price is missing', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner->givePermissionTo(Right::SEE_PRICES->value);
        $this->actingAs($owner);

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'winning_price' => 90000,
                'our_price' => 100000,
            ])
            ->assertHasNoTableActionErrors();

        $result = $tender->result()->firstOrFail();
        expect((float) $result->price_gap)->toBe(-10000.0);

        $tenderTwo = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tenderTwo, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'winning_price' => 90000,
            ])
            ->assertHasNoTableActionErrors();

        expect($tenderTwo->result()->firstOrFail()->price_gap)->toBeNull();
    });

    it('lets a tender manager edit the result even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);
        $result = TenderResult::factory()->for($tender)->create();

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $result);
    });

    it('hides edit from a user with no link to the tender', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);
        $result = TenderResult::factory()->for($tender)->create();

        Livewire::test(ResultRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $result);
    });
});

describe('lessons learned relation manager', function () {
    it('hides the create action for a non-terminal tender', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::QUALITY]);
        $this->actingAs($owner);

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('hides the create action from a user with no link to a terminal tender', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner record lessons learned on a terminal tender, stamping created_by', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::LOST]);
        $this->actingAs($owner);

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'went_well' => 'Team collaboration on the concept',
                'differently_next_time' => 'Start pricing earlier',
                'process_changes' => 'Involve calculation team from kickoff',
            ])
            ->assertHasNoTableActionErrors();

        $lessonsLearned = $tender->lessonsLearned()->firstOrFail();
        expect($lessonsLearned->created_by)->toBe($owner->id);
        expect($lessonsLearned->went_well)->toBe('Team collaboration on the concept');
    });

    it('hides the create action once a lessons-learned record already exists', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        TenderLessonsLearned::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('rejects blanking out an answer on edit via required-field validation', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id, 'status' => TenderStatus::WON]);
        $lessonsLearned = TenderLessonsLearned::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('edit', $lessonsLearned, data: [
                'went_well' => '',
            ])
            ->assertHasTableActionErrors(['went_well' => 'required']);
    });

    it('lets a tender manager edit lessons learned even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);
        $lessonsLearned = TenderLessonsLearned::factory()->for($tender)->create();

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $lessonsLearned);
    });

    it('hides edit from a user with no link to the tender', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::WON]);
        $lessonsLearned = TenderLessonsLearned::factory()->for($tender)->create();

        Livewire::test(LessonsLearnedRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $lessonsLearned);
    });
});

describe('submission file download', function () {
    it('streams the file for a user within the submission\'s tender category', function () {
        Storage::fake('local');
        $category = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $submission = TenderSubmission::factory()->for($tender)->create();
        $file = TenderSubmissionFile::factory()->for($submission, 'submission')->create([
            'file_path' => 'tender-submission-files/bid.pdf',
        ]);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $category->id]))
            ->get($file->downloadUrl())
            ->assertOk();
    });

    it('returns 404 for a user outside the submission\'s tender category', function () {
        Storage::fake('local');
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $submission = TenderSubmission::factory()->for($tender)->create();
        $file = TenderSubmissionFile::factory()->for($submission, 'submission')->create([
            'file_path' => 'tender-submission-files/bid.pdf',
        ]);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryB->id]))
            ->get($file->downloadUrl())
            ->assertNotFound();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $file = TenderSubmissionFile::factory()->create(['file_path' => 'tender-submission-files/bid.pdf']);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get(route('tender-submission-files.download', $file))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $file = TenderSubmissionFile::factory()->create(['file_path' => 'tender-submission-files/bid.pdf']);
        Storage::disk('local')->put($file->file_path, 'contents');
        $expiredUrl = $file->downloadUrl();

        $this->travel(6)->minutes();

        $this->actingAs(User::factory()->create())
            ->get($expiredUrl)
            ->assertForbidden();
    });
});

describe('document requests relation manager', function () {
    it('lists only the tender\'s own document requests', function () {
        $tender = Tender::factory()->create();
        $request = TenderDocumentRequest::factory()->for($tender)->create();
        $foreignRequest = TenderDocumentRequest::factory()->create();

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$request])
            ->assertCanNotSeeTableRecords([$foreignRequest]);
    });

    it('hides the create action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('create');
    });

    it('lets the tender owner create a document request, stamping created_by, with no communication link', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'description' => 'Provide updated insurance certificate',
                'owner_id' => $owner->id,
                'deadline' => now()->addWeek()->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $request = $tender->documentRequests()->firstOrFail();
        expect($request->created_by)->toBe($owner->id);
        expect($request->tender_communication_id)->toBeNull();
        expect($request->status)->toBe(DocumentRequestStatus::OPEN);
    });

    it('lets a document request be linked to a communication entry it arose from', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $communication = TenderCommunication::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'description' => 'Follow up on bidder question',
                'tender_communication_id' => $communication->id,
                'owner_id' => $owner->id,
            ])
            ->assertHasNoTableActionErrors();

        $request = $tender->documentRequests()->firstOrFail();
        expect($request->tender_communication_id)->toBe($communication->id);
    });

    it('lets a tender manager edit a document request even when unrelated to the tender', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $tender = Tender::factory()->create();
        $request = TenderDocumentRequest::factory()->for($tender)->create();

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('edit', $request);
    });

    it('hides edit from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $request = TenderDocumentRequest::factory()->for($tender)->create();

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('edit', $request);
    });

    it('lets a linked user upload a file to a document request', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $request = TenderDocumentRequest::factory()->for($tender)->create();
        $this->actingAs($owner);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('uploadFile', $request)
            ->callTableAction('uploadFile', record: $request, data: [
                'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $file = $request->files()->firstOrFail();
        expect($file->uploaded_by)->toBe($owner->id);
        expect($file->original_filename)->toBe('certificate.pdf');
        expect(Storage::disk('local')->exists($file->file_path))->toBeTrue();
    });

    it('hides the upload file action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $request = TenderDocumentRequest::factory()->for($tender)->create();

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('uploadFile', $request);
    });

    it('lets a linked user change a document request\'s status, writing an audit row', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $request = TenderDocumentRequest::factory()->for($tender)->create(['status' => DocumentRequestStatus::OPEN]);
        $this->actingAs($owner);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('changeStatus', $request)
            ->callTableAction('changeStatus', record: $request, data: [
                'status' => DocumentRequestStatus::FULFILLED->value,
                'reason' => 'Certificate received',
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        expect($request->status)->toBe(DocumentRequestStatus::FULFILLED);
        $change = $request->statusChanges()->firstOrFail();
        expect($change->from_status)->toBe(DocumentRequestStatus::OPEN);
        expect($change->to_status)->toBe(DocumentRequestStatus::FULFILLED);
        expect($change->reason)->toBe('Certificate received');
    });

    it('hides the change status action once a document request is terminal', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $request = TenderDocumentRequest::factory()->for($tender)->create(['status' => DocumentRequestStatus::FULFILLED]);
        $this->actingAs($owner);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('changeStatus', $request);
    });

    it('hides the change status action from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();
        $request = TenderDocumentRequest::factory()->for($tender)->create(['status' => DocumentRequestStatus::OPEN]);

        Livewire::test(DocumentRequestsRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('changeStatus', $request);
    });
});

describe('document request file download', function () {
    it('streams the file for a user within the document request\'s tender category', function () {
        Storage::fake('local');
        $category = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $request = TenderDocumentRequest::factory()->for($tender)->create();
        $file = TenderDocumentRequestFile::factory()->for($request, 'documentRequest')->create([
            'file_path' => 'tender-document-request-files/certificate.pdf',
        ]);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $category->id]))
            ->get($file->downloadUrl())
            ->assertOk();
    });

    it('returns 404 for a user outside the document request\'s tender category', function () {
        Storage::fake('local');
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $tender = Tender::factory()->create(['service_category_id' => $categoryA->id]);
        $request = TenderDocumentRequest::factory()->for($tender)->create();
        $file = TenderDocumentRequestFile::factory()->for($request, 'documentRequest')->create([
            'file_path' => 'tender-document-request-files/certificate.pdf',
        ]);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryB->id]))
            ->get($file->downloadUrl())
            ->assertNotFound();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $file = TenderDocumentRequestFile::factory()->create(['file_path' => 'tender-document-request-files/certificate.pdf']);
        Storage::disk('local')->put($file->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get(route('tender-document-request-files.download', $file))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $file = TenderDocumentRequestFile::factory()->create(['file_path' => 'tender-document-request-files/certificate.pdf']);
        Storage::disk('local')->put($file->file_path, 'contents');
        $expiredUrl = $file->downloadUrl();

        $this->travel(6)->minutes();

        $this->actingAs(User::factory()->create())
            ->get($expiredUrl)
            ->assertForbidden();
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

describe('reference library: references relation manager', function () {
    it('lists only the tender\'s own attached references', function () {
        $tender = Tender::factory()->create();
        $reference = Reference::factory()->create();
        $tender->bidReferences()->attach($reference->id);
        $foreignReference = Reference::factory()->create();

        Livewire::test(ReferencesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$reference])
            ->assertCanNotSeeTableRecords([$foreignReference]);
    });

    it('hides attach/detach from a user with no link to the tender', function () {
        $tender = Tender::factory()->create();

        Livewire::test(ReferencesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('attach');
    });

    it('lets the tender owner attach and detach a reference', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $reference = Reference::factory()->create();
        $this->actingAs($owner);

        Livewire::test(ReferencesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('attach', data: ['recordId' => $reference->id])
            ->assertHasNoTableActionErrors();

        expect($tender->bidReferences->pluck('id')->all())->toBe([$reference->id]);

        Livewire::test(ReferencesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('detach', record: $reference)
            ->assertHasNoTableActionErrors();

        expect($tender->fresh()->bidReferences)->toBeEmpty();
    });
});

describe('reference library: certificates relation manager', function () {
    it('hides attach/detach from a user without the manage-certificates right', function () {
        $tender = Tender::factory()->create();
        $certificate = Certificate::factory()->create();
        $tender->certificates()->attach($certificate->id);

        Livewire::test(CertificatesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('attach')
            ->assertTableActionHidden('detach', record: $certificate);
    });

    it('lets a manage-certificates right holder attach and detach a certificate', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->givePermissionTo(Right::MANAGE_CERTIFICATES->value);
        $tender = Tender::factory()->create();
        $certificate = Certificate::factory()->create();

        Livewire::test(CertificatesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('attach', data: ['recordId' => $certificate->id])
            ->assertHasNoTableActionErrors();

        expect($tender->certificates->pluck('id')->all())->toBe([$certificate->id]);

        Livewire::test(CertificatesRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('detach', record: $certificate)
            ->assertHasNoTableActionErrors();

        expect($tender->fresh()->certificates)->toBeEmpty();
    });
});

describe('reference library: concept blocks relation manager', function () {
    it('pins the block\'s current version at attach time', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);
        $block = ConceptBlock::factory()->create();
        $v1 = ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1]);
        $this->actingAs($owner);

        Livewire::test(ConceptBlocksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('attach', data: ['recordId' => $block->id])
            ->assertHasNoTableActionErrors();

        $pivot = $tender->conceptBlocks()->firstOrFail()->pivot;
        expect($pivot->concept_block_version_id)->toBe($v1->id);
    });

    it('lists only the tender\'s own attached concept blocks', function () {
        $tender = Tender::factory()->create();
        $block = ConceptBlock::factory()->create();
        ConceptBlockVersion::factory()->create(['concept_block_id' => $block->id, 'version_number' => 1]);
        $tender->conceptBlocks()->attach($block->id, ['concept_block_version_id' => $block->currentVersion->id]);
        $foreignBlock = ConceptBlock::factory()->create();

        Livewire::test(ConceptBlocksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$block])
            ->assertCanNotSeeTableRecords([$foreignBlock]);
    });
});
