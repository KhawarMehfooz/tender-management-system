<?php

use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\TasksRelationManager;
use App\Models\ServiceCategory;
use App\Models\Task;
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
    it('creates a task through the form with valid data', function () {
        $tender = Tender::factory()->create();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Prepare cost calculation',
                'priority' => TaskPriority::HIGH->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Task::where('title', 'Prepare cost calculation')->exists())->toBeTrue();
    });

    it('rejects a missing required field', function () {
        $tender = Tender::factory()->create();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => null,
                'priority' => TaskPriority::HIGH->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    });

    it('forces the creator to the acting user regardless of what is submitted', function () {
        $tender = Tender::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Prepare concept document',
                'priority' => TaskPriority::MEDIUM->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('title', 'Prepare concept document')->firstOrFail();
        expect($task->creator_id)->toBe($user->id);
    });
});

describe('deletion', function () {
    it('never authorizes deleting a task, even for the acting user', function () {
        $task = Task::factory()->create();

        expect(TaskResource::canDelete($task))->toBeFalse();
        expect(TaskResource::canDeleteAny())->toBeFalse();
    });

    it('offers no delete action on the edit page', function () {
        $task = Task::factory()->create();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    });
});

describe('status change action', function () {
    it('moves the task to the chosen status and logs who changed it', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('changeStatus')->table($task), [
                'status' => TaskStatus::IN_PROGRESS->value,
                'reason' => 'Starting work',
            ])
            ->assertHasNoFormErrors();

        $task->refresh();
        expect($task->status)->toBe(TaskStatus::IN_PROGRESS);
        expect($task->statusChanges()->first()->changed_by)->toBe($user->id);
    });

    it('only offers the currently valid next statuses', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('changeStatus')->table($task), [
                'status' => TaskStatus::IN_REVIEW->value,
            ])
            ->assertHasFormErrors(['status']);
    });

    it('hides the action once the task is done', function () {
        $task = Task::factory()->create(['status' => TaskStatus::DONE]);

        Livewire::test(ListTasks::class)
            ->assertActionHidden(TestAction::make('changeStatus')->table($task));
    });
});

describe('assignment', function () {
    it('disables the owner, reviewer, and participants fields for a user without task-management rights', function () {
        Livewire::test(CreateTask::class)
            ->assertFormFieldIsDisabled('owner_id')
            ->assertFormFieldIsDisabled('reviewer_id')
            ->assertFormFieldIsDisabled('participants');
    });

    it('enables the owner, reviewer, and participants fields for a team lead', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        Livewire::test(CreateTask::class)
            ->assertFormFieldIsEnabled('owner_id')
            ->assertFormFieldIsEnabled('reviewer_id')
            ->assertFormFieldIsEnabled('participants');
    });

    it('defaults the owner to the creating user when they lack task-management rights', function () {
        $tender = Tender::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Collect certificates',
                'priority' => TaskPriority::LOW->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('title', 'Collect certificates')->firstOrFail();
        expect($task->owner_id)->toBe($user->id);
    });

    it('lets a team lead set the owner and reviewer on create', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $tender = Tender::factory()->create();
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Quality check documents',
                'priority' => TaskPriority::HIGH->value,
                'owner_id' => $owner->id,
                'reviewer_id' => $reviewer->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('title', 'Quality check documents')->firstOrFail();
        expect($task->owner_id)->toBe($owner->id);
        expect($task->reviewer_id)->toBe($reviewer->id);
    });

    it('strips a smuggled owner value server-side when creating without task-management rights', function () {
        $tender = Tender::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Upload bid documents',
                'priority' => TaskPriority::MEDIUM->value,
                'owner_id' => $otherUser->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('title', 'Upload bid documents')->firstOrFail();
        expect($task->owner_id)->toBe($user->id);
    });

    it('keeps the existing owner when a user without task-management rights tries to change it on edit', function () {
        $originalOwner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $originalOwner->id]);
        $otherUser = User::factory()->create();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['owner_id' => $otherUser->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($task->refresh()->owner_id)->toBe($originalOwner->id);
    });

    it('lets a team lead reassign the owner on edit', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);

        $task = Task::factory()->create();
        $newOwner = User::factory()->create();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['owner_id' => $newOwner->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($task->refresh()->owner_id)->toBe($newOwner->id);
    });
});

describe('checklist', function () {
    it('saves checklist items via the repeater relationship', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();

        Livewire::test(CreateTask::class)
            ->fillForm([
                'tender_id' => $tender->id,
                'title' => 'Prepare submission package',
                'priority' => TaskPriority::HIGH->value,
                'checklistItems' => [
                    ['description' => 'Attach cover letter'],
                    ['description' => 'Attach signed forms'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::where('title', 'Prepare submission package')->firstOrFail();
        expect($task->checklistItems()->count())->toBe(2);
        expect($task->checklistItems()->pluck('description')->all())
            ->toBe(['Attach cover letter', 'Attach signed forms']);
    });

    it('toggles is_done on a checklist item', function () {
        $task = Task::factory()->create();
        $item = $task->checklistItems()->create(['description' => 'Attach cover letter', 'is_done' => false]);

        $item->update(['is_done' => true]);

        expect($item->fresh()->is_done)->toBeTrue();
    });
});

describe('view page', function () {
    it('shows the status history for a task with recorded changes', function () {
        $task = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $task->changeStatusTo(TaskStatus::IN_PROGRESS, User::factory()->create(), 'Starting work');

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('Starting work');
    });
});

describe('category-scoped views', function () {
    it('shows a management user (no assigned category) tasks from every category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $taskA = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryA->id])]);
        $taskB = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryB->id])]);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$taskA, $taskB]);
    });

    it('scopes a category-assigned user to only tasks under their own category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $taskA = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryA->id])]);
        $taskB = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryB->id])]);

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryA->id]));

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$taskA])
            ->assertCanNotSeeTableRecords([$taskB]);
    });

    it('blocks a category-scoped user from viewing a task outside their category', function () {
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $foreignTask = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryB->id])]);

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryA->id]));

        expect(fn () => Livewire::test(ViewTask::class, ['record' => $foreignTask->getRouteKey()]))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('tasks relation manager on a tender', function () {
    it('lists the tender\'s own tasks', function () {
        $tender = Tender::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id]);
        $foreignTask = Task::factory()->create();

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertCanSeeTableRecords([$task])
            ->assertCanNotSeeTableRecords([$foreignTask]);
    });

    it('creates a task scoped to the tender without a tender field in the form', function () {
        $tender = Tender::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('create', data: [
                'title' => 'Draft technical concept',
                'priority' => TaskPriority::MEDIUM->value,
            ])
            ->assertHasNoTableActionErrors();

        $task = Task::where('title', 'Draft technical concept')->firstOrFail();
        expect($task->tender_id)->toBe($tender->id);
        expect($task->creator_id)->toBe($user->id);
        expect($task->owner_id)->toBe($user->id);
    });

    it('keeps the existing owner when a user without task-management rights edits through the relation manager', function () {
        $tender = Tender::factory()->create();
        $originalOwner = User::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id, 'owner_id' => $originalOwner->id]);
        $otherUser = User::factory()->create();

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->callTableAction('edit', record: $task, data: ['owner_id' => $otherUser->id])
            ->assertHasNoTableActionErrors();

        expect($task->refresh()->owner_id)->toBe($originalOwner->id);
    });

    it('is read-only on the tender\'s view page', function () {
        $tender = Tender::factory()->create();

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => ViewTender::class])
            ->assertTableActionHidden('create');
    });
});
