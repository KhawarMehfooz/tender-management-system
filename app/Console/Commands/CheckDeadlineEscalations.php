<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification as NotificationMessage;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Implements the 4 escalation levels. Escalation state is one-directional (the
 * highest level reached so far, per [[deadlines]]) — Task.escalation_level only ever holds
 * ASSIGNEE/TEAM_LEAD (levels 1-2, task-overdue), TenderDeadline.escalation_level on the
 * tender's canonical SUBMISSION row only ever holds ADMINISTRATOR/MANAGEMENT (levels 3-4,
 * submission-deadline-driven) — so each level only fires once per task/tender until a human
 * resets it (no reset path exists yet, matching the "not yet built" scope of this task).
 */
#[Signature('tenders:check-deadline-escalations')]
#[Description('Escalates overdue tasks and critical tenders nearing their submission deadline.')]
class CheckDeadlineEscalations extends Command
{
    public function handle(): int
    {
        $this->escalateOverdueTasks();
        $this->escalateSubmissionDeadlines();

        return self::SUCCESS;
    }

    /**
     * Levels 1-2: notify the task owner once it's overdue, then the tender owner once it's
     * been overdue for 24 hours.
     */
    private function escalateOverdueTasks(): void
    {
        Task::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', TaskStatus::DONE)
            ->with(['owner', 'tender.owner'])
            ->get()
            ->each(function (Task $task): void {
                $hoursOverdue = $task->due_date->diffInHours(now());
                $currentLevel = $task->escalation_level?->level() ?? 0;

                if ($currentLevel < EscalationLevel::ASSIGNEE->level()) {
                    $this->notifyWithCover($task->owner, new TaskEscalatedToAssigneeNotification($task));
                    $task->forceFill([
                        'escalation_level' => EscalationLevel::ASSIGNEE,
                        'last_escalated_at' => now(),
                    ])->save();
                    $currentLevel = EscalationLevel::ASSIGNEE->level();
                }

                if ($hoursOverdue >= 24 && $currentLevel < EscalationLevel::TEAM_LEAD->level()) {
                    $this->notifyWithCover($task->tender->owner, new TaskEscalatedToTeamLeadNotification($task));
                    $task->forceFill([
                        'escalation_level' => EscalationLevel::TEAM_LEAD,
                        'last_escalated_at' => now(),
                    ])->save();
                }
            });
    }

    /**
     * Notifies $recipient as normal, then additionally notifies their absence cover when
     * $recipient has an active UserAbsence (covering "now") with a cover_user_id set — the
     * original recipient is always notified too, an absent owner is never silently skipped.
     */
    private function notifyWithCover(User $recipient, NotificationMessage $notification): void
    {
        $recipient->notify($notification);

        $cover = $recipient->absences()
            ->get()
            ->first(fn (UserAbsence $absence): bool => $absence->covers(now()))
            ?->coverUser;

        $cover?->notify($notification);
    }

    /**
     * Levels 3-4: add the administrator when a critical (urgent-priority) task is still open
     * with less than 48 hours before the tender's submission deadline, then raise a
     * management alert under 24 hours with critical items still open. Both land on every
     * super admin — this app has no distinct administrator/management role.
     */
    private function escalateSubmissionDeadlines(): void
    {
        // Guard against the role simply not existing yet (e.g. a fresh install before
        // RolesAndPermissionsSeeder runs) — User::role() throws RoleDoesNotExist otherwise.
        if (! Role::where('name', RoleName::SUPER_ADMIN->value)->exists()) {
            return;
        }

        $admins = User::role(RoleName::SUPER_ADMIN->value)->get();

        if ($admins->isEmpty()) {
            return;
        }

        Tender::query()
            ->whereHas('deadlines', fn ($query) => $query->where('type', DeadlineType::SUBMISSION))
            ->get()
            ->each(function (Tender $tender) use ($admins): void {
                $deadline = $tender->submissionDeadline();

                if ($deadline === null || $deadline->due_at->isPast()) {
                    return;
                }

                $criticalTasks = $tender->tasks()
                    ->where('priority', TaskPriority::URGENT)
                    ->where('status', '!=', TaskStatus::DONE)
                    ->get();

                if ($criticalTasks->isEmpty()) {
                    return;
                }

                $hoursUntilSubmission = now()->diffInHours($deadline->due_at);
                $currentLevel = $deadline->escalation_level?->level() ?? 0;

                if ($hoursUntilSubmission <= 48 && $currentLevel < EscalationLevel::ADMINISTRATOR->level()) {
                    Notification::send($admins, new TaskEscalatedToAdministratorNotification($criticalTasks->first()));
                    $deadline->forceFill([
                        'escalation_level' => EscalationLevel::ADMINISTRATOR,
                        'last_escalated_at' => now(),
                    ])->save();
                    $currentLevel = EscalationLevel::ADMINISTRATOR->level();
                }

                if ($hoursUntilSubmission <= 24 && $currentLevel < EscalationLevel::MANAGEMENT->level()) {
                    Notification::send($admins, new TenderEscalatedToManagementNotification($tender, $criticalTasks->count()));
                    $deadline->forceFill([
                        'escalation_level' => EscalationLevel::MANAGEMENT,
                        'last_escalated_at' => now(),
                    ])->save();
                }
            });
    }
}
