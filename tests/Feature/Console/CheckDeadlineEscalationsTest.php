<?php

use App\Console\Commands\CheckDeadlineEscalations;
use App\Enums\AbsenceType;
use App\Enums\DeadlineType;
use App\Enums\EscalationLevel;
use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use App\Models\UserAbsence;
use App\Notifications\TaskEscalatedToAdministratorNotification;
use App\Notifications\TaskEscalatedToAssigneeNotification;
use App\Notifications\TaskEscalatedToTeamLeadNotification;
use App\Notifications\TenderEscalatedToManagementNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

describe('task overdue escalation (levels 1-2)', function () {
    it('notifies the owner once a task is overdue and records level 1', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->startOfDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($owner, TaskEscalatedToAssigneeNotification::class);
        expect($task->refresh()->escalation_level)->toBe(EscalationLevel::ASSIGNEE);
    });

    it('does not escalate a task that is not yet overdue', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->addDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertNothingSent();
        expect($task->refresh()->escalation_level)->toBeNull();
    });

    it('does not escalate a done task even if its due date has passed', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->subWeek(), 'status' => TaskStatus::DONE]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('also notifies the tender owner once a task has been overdue for 24 hours', function () {
        Notification::fake();

        $tenderOwner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $tenderOwner->id]);
        $taskOwner = User::factory()->create();
        $task = Task::factory()->create([
            'tender_id' => $tender->id,
            'owner_id' => $taskOwner->id,
            'due_date' => now()->subDay()->startOfDay(),
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($taskOwner, TaskEscalatedToAssigneeNotification::class);
        Notification::assertSentTo($tenderOwner, TaskEscalatedToTeamLeadNotification::class);
        expect($task->refresh()->escalation_level)->toBe(EscalationLevel::TEAM_LEAD);
    });

    it('does not re-notify a task already escalated to level 1 on a second run', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->startOfDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();
        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentToTimes($owner, TaskEscalatedToAssigneeNotification::class, 1);
    });

    it('also notifies the owner\'s absence cover at level 1', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $cover = User::factory()->create();
        UserAbsence::factory()->create([
            'user_id' => $owner->id,
            'type' => AbsenceType::HOLIDAY,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'cover_user_id' => $cover->id,
        ]);
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->startOfDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($owner, TaskEscalatedToAssigneeNotification::class);
        Notification::assertSentTo($cover, TaskEscalatedToAssigneeNotification::class);
    });

    it('also notifies the tender owner\'s absence cover at level 2', function () {
        Notification::fake();

        $tenderOwner = User::factory()->create();
        $cover = User::factory()->create();
        UserAbsence::factory()->create([
            'user_id' => $tenderOwner->id,
            'type' => AbsenceType::SICKNESS,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'cover_user_id' => $cover->id,
        ]);
        $tender = Tender::factory()->create(['owner_id' => $tenderOwner->id]);
        $taskOwner = User::factory()->create();
        Task::factory()->create([
            'tender_id' => $tender->id,
            'owner_id' => $taskOwner->id,
            'due_date' => now()->subDay()->startOfDay(),
            'status' => TaskStatus::IN_PROGRESS,
        ]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($tenderOwner, TaskEscalatedToTeamLeadNotification::class);
        Notification::assertSentTo($cover, TaskEscalatedToTeamLeadNotification::class);
    });

    it('does not change recipients when the owner has no absence', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->startOfDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentToTimes($owner, TaskEscalatedToAssigneeNotification::class, 1);
        Notification::assertCount(1);
    });

    it('does not error when the absent owner has no cover assigned', function () {
        Notification::fake();

        $owner = User::factory()->create();
        UserAbsence::factory()->create([
            'user_id' => $owner->id,
            'type' => AbsenceType::OTHER,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'cover_user_id' => null,
        ]);
        $task = Task::factory()->create(['owner_id' => $owner->id, 'due_date' => now()->startOfDay(), 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentToTimes($owner, TaskEscalatedToAssigneeNotification::class, 1);
        Notification::assertCount(1);
    });
});

describe('submission deadline escalation (levels 3-4)', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create())->assignRole(RoleName::SUPER_ADMIN);
    });

    it('notifies every super admin when a critical task is open under 48 hours before submission', function () {
        Notification::fake();

        $tender = Tender::factory()->create();
        $tender->upsertDeadline(DeadlineType::SUBMISSION, now()->addHours(36));
        Task::factory()->create(['tender_id' => $tender->id, 'priority' => TaskPriority::URGENT, 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($this->admin, TaskEscalatedToAdministratorNotification::class);
        expect($tender->submissionDeadline()->fresh()->escalation_level)->toBe(EscalationLevel::ADMINISTRATOR);
    });

    it('raises a management alert with the open critical task count under 24 hours before submission', function () {
        Notification::fake();

        $tender = Tender::factory()->create();
        $tender->upsertDeadline(DeadlineType::SUBMISSION, now()->addHours(12));
        Task::factory()->count(2)->create(['tender_id' => $tender->id, 'priority' => TaskPriority::URGENT, 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentTo($this->admin, TenderEscalatedToManagementNotification::class, function ($notification) {
            return $notification->openCriticalTaskCount === 2;
        });
        expect($tender->submissionDeadline()->fresh()->escalation_level)->toBe(EscalationLevel::MANAGEMENT);
    });

    it('does not escalate when no critical task is open', function () {
        Notification::fake();

        $tender = Tender::factory()->create();
        $tender->upsertDeadline(DeadlineType::SUBMISSION, now()->addHours(12));
        Task::factory()->create(['tender_id' => $tender->id, 'priority' => TaskPriority::LOW, 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertNotSentTo($this->admin, TaskEscalatedToAdministratorNotification::class);
        Notification::assertNotSentTo($this->admin, TenderEscalatedToManagementNotification::class);
    });

    it('does not escalate when the submission deadline is more than 48 hours away', function () {
        Notification::fake();

        $tender = Tender::factory()->create();
        $tender->upsertDeadline(DeadlineType::SUBMISSION, now()->addDays(5));
        Task::factory()->create(['tender_id' => $tender->id, 'priority' => TaskPriority::URGENT, 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('does not re-notify a tender already escalated to administrator on a second run', function () {
        Notification::fake();

        $tender = Tender::factory()->create();
        $tender->upsertDeadline(DeadlineType::SUBMISSION, now()->addHours(36));
        Task::factory()->create(['tender_id' => $tender->id, 'priority' => TaskPriority::URGENT, 'status' => TaskStatus::IN_PROGRESS]);

        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();
        $this->artisan(CheckDeadlineEscalations::class)->assertSuccessful();

        Notification::assertSentToTimes($this->admin, TaskEscalatedToAdministratorNotification::class, 1);
    });
});
