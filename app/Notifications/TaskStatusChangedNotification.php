<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public TaskStatus $fromStatus,
        public TaskStatus $toStatus,
        public User $actor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::TASK_STATUS_CHANGED) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.task_status_changed.mail_subject', ['task' => $this->task->title]))
            ->line(__('notifications.task_status_changed.mail_line', [
                'task' => $this->task->title,
                'from' => $this->fromStatus->getLabel(),
                'to' => $this->toStatus->getLabel(),
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
            ->title(__('notifications.task_status_changed.title', ['task' => $this->task->title]))
            ->body(__('notifications.task_status_changed.body', [
                'from' => $this->fromStatus->getLabel(),
                'to' => $this->toStatus->getLabel(),
            ]))
            ->getDatabaseMessage();
    }
}
