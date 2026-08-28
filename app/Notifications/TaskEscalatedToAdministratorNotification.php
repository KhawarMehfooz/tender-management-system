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
 * Escalation level 3 (idea.md M3): add the administrator when a critical (urgent-priority)
 * task is still open with less than 48 hours left before its tender's submission deadline.
 * Recipients are every RoleName::SUPER_ADMIN user — this app has no distinct "administrator"
 * role, see [[milestones]].
 */
class TaskEscalatedToAdministratorNotification extends Notification implements ShouldQueue
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
            $notifiable->wantsEmailFor(NotificationType::TASK_ESCALATED_ADMINISTRATOR) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.task_escalated_administrator.mail_subject', ['task' => $this->task->title]))
            ->line(__('notifications.task_escalated_administrator.mail_line', [
                'task' => $this->task->title,
                'tender' => $this->task->tender->title,
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
            ->title(__('notifications.task_escalated_administrator.title', ['task' => $this->task->title]))
            ->body(__('notifications.task_escalated_administrator.body', ['tender' => $this->task->tender->title]))
            ->getDatabaseMessage();
    }
}
