<?php

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\Tender;
use App\Models\User;
use App\Notifications\TaskEscalatedToAdministratorNotification;
use App\Notifications\TaskEscalatedToAssigneeNotification;
use App\Notifications\TaskEscalatedToTeamLeadNotification;
use App\Notifications\TenderEscalatedToManagementNotification;
use Illuminate\Support\Facades\Notification;

describe('task escalated to assignee', function () {
    it('notifies the task owner via database and mail', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $task = Task::factory()->create(['owner_id' => $owner->id]);

        $owner->notify(new TaskEscalatedToAssigneeNotification($task));

        Notification::assertSentTo($owner, TaskEscalatedToAssigneeNotification::class, function ($notification, $channels) {
            return in_array('database', $channels, true) && in_array('mail', $channels, true);
        });
    });

    it('skips the mail channel when the owner opted out', function () {
        Notification::fake();

        $owner = User::factory()->create();
        NotificationPreference::factory()->for($owner)->create([
            'notification_type' => NotificationType::TASK_ESCALATED_ASSIGNEE,
            'email_enabled' => false,
        ]);
        $task = Task::factory()->create(['owner_id' => $owner->id]);

        $owner->notify(new TaskEscalatedToAssigneeNotification($task));

        Notification::assertSentTo($owner, TaskEscalatedToAssigneeNotification::class, function ($notification, $channels) {
            return ! in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
    });
});

describe('task escalated to team lead', function () {
    it('notifies the tender owner', function () {
        Notification::fake();

        $tenderOwner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $tenderOwner->id]);
        $task = Task::factory()->create(['tender_id' => $tender->id]);

        $tenderOwner->notify(new TaskEscalatedToTeamLeadNotification($task));

        Notification::assertSentTo($tenderOwner, TaskEscalatedToTeamLeadNotification::class);
    });
});

describe('task escalated to administrator', function () {
    it('notifies a super admin', function () {
        Notification::fake();

        $admin = User::factory()->create();
        $task = Task::factory()->create();

        $admin->notify(new TaskEscalatedToAdministratorNotification($task));

        Notification::assertSentTo($admin, TaskEscalatedToAdministratorNotification::class);
    });
});

describe('tender escalated to management', function () {
    it('notifies a super admin with the open critical task count', function () {
        Notification::fake();

        $admin = User::factory()->create();
        $tender = Tender::factory()->create();

        $admin->notify(new TenderEscalatedToManagementNotification($tender, 3));

        Notification::assertSentTo($admin, TenderEscalatedToManagementNotification::class, function ($notification) {
            return $notification->openCriticalTaskCount === 3;
        });
    });

    it('skips the mail channel when the recipient opted out', function () {
        Notification::fake();

        $admin = User::factory()->create();
        NotificationPreference::factory()->for($admin)->create([
            'notification_type' => NotificationType::TENDER_ESCALATED_MANAGEMENT,
            'email_enabled' => false,
        ]);
        $tender = Tender::factory()->create();

        $admin->notify(new TenderEscalatedToManagementNotification($tender, 1));

        Notification::assertSentTo($admin, TenderEscalatedToManagementNotification::class, function ($notification, $channels) {
            return ! in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
    });
});
