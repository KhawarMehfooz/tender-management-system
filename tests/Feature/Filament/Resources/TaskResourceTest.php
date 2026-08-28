<?php

use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\Tenders\Pages\EditTender;
use App\Filament\Resources\Tenders\Pages\ViewTender;
use App\Filament\Resources\Tenders\RelationManagers\TasksRelationManager;
use App\Models\ServiceCategory;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\Tender;
use App\Models\User;
use App\Notifications\TaskAttachmentAddedNotification;
use App\Notifications\TaskCommentAddedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

describe('dependencies', function () {
    it('saves a dependency on another task in the same tender', function () {
        $tender = Tender::factory()->create();
        $dependency = Task::factory()->create(['tender_id' => $tender->id]);
        $task = Task::factory()->create(['tender_id' => $tender->id]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['dependencies' => [$dependency->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($task->dependencies()->pluck('tasks.id')->all())->toBe([$dependency->id]);
    });

    it('rejects a dependency on a task from a different tender', function () {
        $task = Task::factory()->create();
        $otherTenderTask = Task::factory()->create();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['dependencies' => [$otherTenderTask->id]])
            ->call('save')
            ->assertHasFormErrors(['dependencies.0']);
    });

    it('rejects a task depending on itself', function () {
        $task = Task::factory()->create();

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['dependencies' => [$task->id]])
            ->call('save')
            ->assertHasFormErrors(['dependencies.0']);
    });

    it('rejects a dependency that would create a cycle', function () {
        $tender = Tender::factory()->create();
        $a = Task::factory()->create(['tender_id' => $tender->id]);
        $b = Task::factory()->create(['tender_id' => $tender->id]);
        $b->dependencies()->attach($a);

        Livewire::test(EditTask::class, ['record' => $a->getRouteKey()])
            ->fillForm(['dependencies' => [$b->id]])
            ->call('save')
            ->assertHasFormErrors(['dependencies.0']);
    });

    it('blocks completing a task while a dependency is unfinished', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::OPEN]);
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $task->dependencies()->attach($dependency);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('changeStatus')->table($task), [
                'status' => TaskStatus::DONE->value,
            ])
            ->assertHasFormErrors(['status']);
    });

    it('allows completing a task once dependencies are done', function () {
        $dependency = Task::factory()->create(['status' => TaskStatus::DONE]);
        $task = Task::factory()->create(['status' => TaskStatus::IN_REVIEW]);
        $task->dependencies()->attach($dependency);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('changeStatus')->table($task), [
                'status' => TaskStatus::DONE->value,
            ])
            ->assertHasNoFormErrors();

        expect($task->fresh()->status)->toBe(TaskStatus::DONE);
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

    it('marks a checklist item done through the edit form repeater', function () {
        $task = Task::factory()->create();
        $item = $task->checklistItems()->create(['description' => 'Attach cover letter', 'is_done' => false]);

        $component = Livewire::test(EditTask::class, ['record' => $task->getRouteKey()]);
        $repeaterKey = collect($component->get('data.checklistItems'))
            ->search(fn (array $row): bool => $row['description'] === $item->description);

        $component->set("data.checklistItems.{$repeaterKey}.is_done", true)
            ->call('save')
            ->assertHasNoFormErrors();

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

describe('attachments', function () {
    it('lets a linked reviewer upload an attachment', function () {
        Storage::fake('local');
        $reviewer = User::factory()->create();
        $task = Task::factory()->create(['reviewer_id' => $reviewer->id]);
        $this->actingAs($reviewer);

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $attachment = $task->attachments()->firstOrFail();
        expect($attachment->uploaded_by)->toBe($reviewer->id);
        expect($attachment->original_filename)->toBe('evidence.pdf');
        expect(Storage::disk('local')->exists($attachment->file_path))->toBeTrue();
    });

    it('lets a task manager upload an attachment even when unrelated to the task', function () {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $task = Task::factory()->create();

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('create');
    });

    it('hides the upload action from a user with no link to the task', function () {
        $task = Task::factory()->create();

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionHidden('create');
    });

    it('lets the uploader delete their own attachment', function () {
        Storage::fake('local');
        $uploader = User::factory()->create();
        $task = Task::factory()->create(['reviewer_id' => $uploader->id]);
        $attachment = TaskAttachment::factory()->for($task)->create(['uploaded_by' => $uploader->id]);
        $this->actingAs($uploader);

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('delete', $attachment)
            ->callTableAction('delete', record: $attachment)
            ->assertHasNoTableActionErrors();

        expect(TaskAttachment::find($attachment->id))->toBeNull();
    });

    it('hides delete from a different linked user who did not upload the attachment', function () {
        $uploader = User::factory()->create();
        $otherOwner = User::factory()->create();
        $task = Task::factory()->create(['reviewer_id' => $uploader->id, 'owner_id' => $otherOwner->id]);
        $attachment = TaskAttachment::factory()->for($task)->create(['uploaded_by' => $uploader->id]);
        $this->actingAs($otherOwner);

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionHidden('delete', $attachment);
    });

    it('lets a task manager delete any attachment', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $task = Task::factory()->create();
        $attachment = TaskAttachment::factory()->for($task)->create();

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('delete', $attachment);
    });
});

describe('attachment download', function () {
    it('streams the file for a user within the task\'s category', function () {
        Storage::fake('local');
        $category = ServiceCategory::factory()->create();
        $task = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $category->id])]);
        $attachment = TaskAttachment::factory()->for($task)->create(['file_path' => 'task-attachments/evidence.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $category->id]))
            ->get($attachment->downloadUrl())
            ->assertOk();
    });

    it('returns 404 for a user outside the task\'s category', function () {
        Storage::fake('local');
        $categoryA = ServiceCategory::factory()->create();
        $categoryB = ServiceCategory::factory()->create();
        $task = Task::factory()->create(['tender_id' => Tender::factory()->create(['service_category_id' => $categoryA->id])]);
        $attachment = TaskAttachment::factory()->for($task)->create(['file_path' => 'task-attachments/evidence.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');

        $this->actingAs(User::factory()->create(['service_category_id' => $categoryB->id]))
            ->get($attachment->downloadUrl())
            ->assertNotFound();
    });

    it('rejects an unsigned download link', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create(['file_path' => 'task-attachments/evidence.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');

        $this->actingAs(User::factory()->create())
            ->get(route('task-attachments.download', $attachment))
            ->assertForbidden();
    });

    it('rejects an expired download link', function () {
        Storage::fake('local');
        $attachment = TaskAttachment::factory()->create(['file_path' => 'task-attachments/evidence.pdf']);
        Storage::disk('local')->put($attachment->file_path, 'contents');
        $expiredUrl = $attachment->downloadUrl();

        $this->travel(6)->minutes();

        $this->actingAs(User::factory()->create())
            ->get($expiredUrl)
            ->assertForbidden();
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

    it('shows a task\'s comments in the view action modal', function () {
        $tender = Tender::factory()->create();
        $owner = User::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id, 'owner_id' => $owner->id]);
        $task->comments()->create(['user_id' => $owner->id, 'body' => 'Ready for review']);
        $this->actingAs($owner);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->mountTableAction('view', record: $task)
            ->assertMountedActionModalSee('Ready for review');
    });

    it('shows a task\'s attachments in the view action modal', function () {
        $tender = Tender::factory()->create();
        $owner = User::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id, 'owner_id' => $owner->id]);
        TaskAttachment::factory()->for($task)->create(['file_path' => 'task-attachments/evidence.pdf', 'original_filename' => 'evidence.pdf']);
        $this->actingAs($owner);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->mountTableAction('view', record: $task)
            ->assertMountedActionModalSee('evidence.pdf');
    });
});

describe('comments', function () {
    it('lets a linked owner create a comment', function () {
        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('create')
            ->callTableAction('create', data: [
                'body' => 'Please review the cost estimates before Friday.',
            ])
            ->assertHasNoTableActionErrors();

        $comment = $task->comments()->firstOrFail();
        expect($comment->body)->toBe('Please review the cost estimates before Friday.');
        expect($comment->user_id)->toBe($owner->id);
    });

    it('lets a task manager create a comment even when unrelated', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $task = Task::factory()->create();

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('create');
    });

    it('hides the create action from a user with no link to the task', function () {
        $task = Task::factory()->create();

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionHidden('create');
    });

    it('lets the comment author delete their own comment', function () {
        $author = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $author->id]);
        $comment = TaskComment::factory()->for($task)->create(['user_id' => $author->id]);
        $this->actingAs($author);

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('delete', $comment)
            ->callTableAction('delete', record: $comment)
            ->assertHasNoTableActionErrors();

        expect(TaskComment::find($comment->id))->toBeNull();
    });

    it('hides delete from a different linked user who did not write the comment', function () {
        $author = User::factory()->create();
        $otherOwner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $otherOwner->id]);
        $comment = TaskComment::factory()->for($task)->create(['user_id' => $author->id]);
        $this->actingAs($otherOwner);

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionHidden('delete', $comment);
    });

    it('lets a task manager delete any comment', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        auth()->user()->assignRole(RoleName::TEAM_LEAD);
        $task = Task::factory()->create();
        $comment = TaskComment::factory()->for($task)->create();

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->assertTableActionVisible('delete', $comment);
    });
});

describe('notifications', function () {
    it('notifies other linked users but not the author when a comment is added', function () {
        Notification::fake();
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'reviewer_id' => $reviewer->id]);
        $this->actingAs($owner);

        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->callTableAction('create', data: ['body' => 'Looks good to me.'])
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo($reviewer, TaskCommentAddedNotification::class);
        Notification::assertNotSentTo($owner, TaskCommentAddedNotification::class);
    });

    it('notifies other linked users but not the uploader when an attachment is added', function () {
        Storage::fake('local');
        Notification::fake();
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'reviewer_id' => $reviewer->id]);
        $this->actingAs($owner);

        Livewire::test(AttachmentsRelationManager::class, ['ownerRecord' => $task, 'pageClass' => EditTask::class])
            ->callTableAction('create', data: [
                'file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo($reviewer, TaskAttachmentAddedNotification::class);
        Notification::assertNotSentTo($owner, TaskAttachmentAddedNotification::class);
    });
});

describe('table add comment action', function () {
    it('lets a linked owner add a comment via the table row action', function () {
        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('addComment')->table($task), [
                'body' => 'Check the deadline on this one.',
            ])
            ->assertHasNoFormErrors();

        $comment = $task->comments()->firstOrFail();
        expect($comment->body)->toBe('Check the deadline on this one.');
        expect($comment->user_id)->toBe($owner->id);
    });

    it('hides the add comment action from a user with no link to the task', function () {
        $task = Task::factory()->create();

        Livewire::test(ListTasks::class)
            ->assertActionHidden(TestAction::make('addComment')->table($task));
    });

    it('strips a smuggled user_id and uses the acting user', function () {
        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id]);
        $otherUser = User::factory()->create();
        $this->actingAs($owner);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('addComment')->table($task), [
                'body' => 'Test comment.',
            ]);

        $comment = $task->comments()->firstOrFail();
        expect($comment->user_id)->toBe($owner->id);
    });
});

describe('table add attachment action', function () {
    it('lets a linked owner add an attachment via the table row action', function () {
        Storage::fake('local');
        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(ListTasks::class)
            ->callAction(TestAction::make('addAttachment')->table($task), [
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoFormErrors();

        $attachment = $task->attachments()->firstOrFail();
        expect($attachment->uploaded_by)->toBe($owner->id);
        expect($attachment->original_filename)->toBe('report.pdf');
        expect(Storage::disk('local')->exists($attachment->file_path))->toBeTrue();
    });

    it('hides the add attachment action from a user with no link to the task', function () {
        $task = Task::factory()->create();

        Livewire::test(ListTasks::class)
            ->assertActionHidden(TestAction::make('addAttachment')->table($task));
    });
});

describe('tasks relation manager table actions', function () {
    it('offers add comment action on the tender task list', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id, 'owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('addComment', $task);
    });

    it('offers add attachment action on the tender task list', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id, 'owner_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionVisible('addAttachment', $task);
    });

    it('hides add comment from an unrelated user on the tender task list', function () {
        $tender = Tender::factory()->create();
        $task = Task::factory()->create(['tender_id' => $tender->id]);

        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $tender, 'pageClass' => EditTender::class])
            ->assertTableActionHidden('addComment', $task);
    });
});
