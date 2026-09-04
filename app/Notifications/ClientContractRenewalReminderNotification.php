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
 * Fired by CheckClientContractRenewals at the 12/9/6-month-before-contract_end_date
 * thresholds. Recipients are the tender's owner plus any TEAM_LEAD/DEPARTMENT_HEAD in the
 * tender's service_category — fires for lost tenders too, not just won ones, per idea.md.
 */
class ClientContractRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tender $tender,
        public int $monthsUntilEnd,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::CLIENT_CONTRACT_RENEWAL) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.client_contract_renewal.mail_subject', ['tender' => $this->tender->title]))
            ->line(__('notifications.client_contract_renewal.mail_line', [
                'tender' => $this->tender->title,
                'client' => $this->tender->contracting_authority,
                'date' => $this->tender->contract_end_date?->toFormattedDateString(),
                'months' => $this->monthsUntilEnd,
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
            ->title(__('notifications.client_contract_renewal.title', ['tender' => $this->tender->title]))
            ->body(__('notifications.client_contract_renewal.body', [
                'client' => $this->tender->contracting_authority,
                'months' => $this->monthsUntilEnd,
            ]))
            ->getDatabaseMessage();
    }
}
