<?php

use App\Enums\DeadlineType;
use App\Enums\Right;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TenderStatus;
use App\Filament\Resources\Tenders\Pages\CreateTender;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ListTenders;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\Tenders\TenderResource;
use App\Models\ProcurementProcedure;
use App\Models\Sector;
use App\Models\ServiceCategory;
use App\Models\Source;
use App\Models\Task;
use App\Models\Tender;
use App\Models\TenderDeadline;
use App\Models\TenderHardDeletion;
use App\Models\TenderTeamMember;
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

    it('rejects moving to submission while a task is not done', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::QUALITY]);
        Task::factory()->create(['tender_id' => $tender->id, 'status' => TaskStatus::OPEN]);

        Livewire::test(ListTenders::class)
            ->callAction(TestAction::make('changeStatus')->table($tender), [
                'status' => TenderStatus::SUBMISSION->value,
            ])
            ->assertHasFormErrors(['status']);

        expect($tender->fresh()->status)->toBe(TenderStatus::QUALITY);
    });

    it('allows moving to submission once every task is done', function () {
        $tender = Tender::factory()->create(['status' => TenderStatus::QUALITY]);
        Task::factory()->create(['tender_id' => $tender->id, 'status' => TaskStatus::DONE]);

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
