<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Tender;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Escalation level 4 (idea.md M3): raise a management alert when less than 24 hours remain
 * before a tender's submission deadline with critical (urgent-priority) items still open.
 * Tender-wide rather than per-task, since idea.md phrases this level as "critical items"
 * (plural), unlike level 3's single-task wording. Recipients are every RoleName::SUPER_ADMIN
 * user — this app has no distinct "management" role, see [[milestones]].
 */
class TenderEscalatedToManagementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tender $tender,
        public int $openCriticalTaskCount,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::TENDER_ESCALATED_MANAGEMENT) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.tender_escalated_management.mail_subject', ['tender' => $this->tender->title]))
            ->line(__('notifications.tender_escalated_management.mail_line', [
                'tender' => $this->tender->title,
                'count' => $this->openCriticalTaskCount,
            ]))
            ->action(
                __('notifications.actions.view_tender'),
                route('filament.admin.resources.tenders.view', $this->tender),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.tender_escalated_management.title', ['tender' => $this->tender->title]))
            ->body(__('notifications.tender_escalated_management.body', ['count' => $this->openCriticalTaskCount]))
            ->getDatabaseMessage();
    }
}
