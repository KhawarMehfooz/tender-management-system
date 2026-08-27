<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\TaskAttachment;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAttachmentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TaskAttachment $attachment) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::TASK_ATTACHMENT_ADDED) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.task_attachment_added.mail_subject', ['task' => $this->attachment->task->title]))
            ->line(__('notifications.task_attachment_added.mail_line', [
                'task' => $this->attachment->task->title,
                'filename' => $this->attachment->original_filename,
                'uploader' => $this->attachment->uploadedBy->name,
            ]))
            ->action(
                __('notifications.actions.view_task'),
                route('filament.admin.resources.tasks.edit', $this->attachment->task),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.task_attachment_added.title', ['task' => $this->attachment->task->title]))
            ->body(__('notifications.task_attachment_added.body', [
                'filename' => $this->attachment->original_filename,
                'uploader' => $this->attachment->uploadedBy->name,
            ]))
            ->getDatabaseMessage();
    }
}
