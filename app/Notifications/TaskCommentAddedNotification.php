<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\TaskComment;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCommentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TaskComment $comment) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::TASK_COMMENT_ADDED) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.task_comment_added.mail_subject', ['task' => $this->comment->task->title]))
            ->line(__('notifications.task_comment_added.mail_line', [
                'task' => $this->comment->task->title,
                'author' => $this->comment->author->name,
            ]))
            ->action(
                __('notifications.actions.view_task'),
                route('filament.admin.resources.tasks.edit', $this->comment->task),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.task_comment_added.title', ['task' => $this->comment->task->title]))
            ->body(__('notifications.task_comment_added.body', ['author' => $this->comment->author->name]))
            ->getDatabaseMessage();
    }
}
