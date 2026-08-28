<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Escalation level 2 (idea.md M3): inform the tender's owner (the "team lead" role for this
 * tender) once a task has been overdue for 24 hours.
 */
class TaskEscalatedToTeamLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::TASK_ESCALATED_TEAM_LEAD) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.task_escalated_team_lead.mail_subject', ['task' => $this->task->title]))
            ->line(__('notifications.task_escalated_team_lead.mail_line', [
                'task' => $this->task->title,
                'owner' => $this->task->owner->name,
            ]))
            ->action(
                __('notifications.actions.view_task'),
                route('filament.admin.resources.tasks.edit', $this->task),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.task_escalated_team_lead.title', ['task' => $this->task->title]))
            ->body(__('notifications.task_escalated_team_lead.body', ['owner' => $this->task->owner->name]))
            ->getDatabaseMessage();
    }
}
