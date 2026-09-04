<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ScheduledReport;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by GenerateScheduledReports once the period's management-reporting PDF has been
 * rendered and stored. Recipients are every SUPER_ADMIN/DEPARTMENT_HEAD, see [[milestones]].
 */
class ScheduledReportGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ScheduledReport $scheduledReport,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::SCHEDULED_REPORT_GENERATED) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.scheduled_report_generated.mail_subject', ['period' => $this->scheduledReport->period_type->getLabel()]))
            ->line(__('notifications.scheduled_report_generated.mail_line', [
                'period' => $this->scheduledReport->period_type->getLabel(),
                'from' => $this->scheduledReport->period_start->toFormattedDateString(),
                'to' => $this->scheduledReport->period_end->toFormattedDateString(),
            ]))
            ->action(__('notifications.actions.view_report'), $this->scheduledReport->downloadUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.scheduled_report_generated.title', ['period' => $this->scheduledReport->period_type->getLabel()]))
            ->body(__('notifications.scheduled_report_generated.body', [
                'from' => $this->scheduledReport->period_start->toFormattedDateString(),
                'to' => $this->scheduledReport->period_end->toFormattedDateString(),
            ]))
            ->getDatabaseMessage();
    }
}
