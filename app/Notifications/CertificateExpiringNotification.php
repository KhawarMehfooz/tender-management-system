<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Certificate;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by CheckCertificateExpiry at the 90/30/7-day thresholds ($thresholdDays set),
 * and once more on actual expiry ($thresholdDays null). Recipients are every
 * Right::MANAGE_CERTIFICATES holder, see [[milestones]].
 */
class CertificateExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Certificate $certificate,
        public ?int $thresholdDays,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return array_filter([
            'database',
            $notifiable->wantsEmailFor(NotificationType::CERTIFICATE_EXPIRING) ? 'mail' : null,
        ]);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.certificate_expiring.mail_subject', ['certificate' => $this->certificate->name]))
            ->line($this->thresholdDays === null
                ? __('notifications.certificate_expiring.mail_line_expired', ['certificate' => $this->certificate->name])
                : __('notifications.certificate_expiring.mail_line_expiring', ['certificate' => $this->certificate->name, 'days' => $this->thresholdDays])
            )
            ->action(
                __('notifications.actions.view_certificate'),
                route('filament.admin.resources.certificates.edit', $this->certificate),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('notifications.certificate_expiring.title', ['certificate' => $this->certificate->name]))
            ->body($this->thresholdDays === null
                ? __('notifications.certificate_expiring.body_expired')
                : __('notifications.certificate_expiring.body_expiring', ['days' => $this->thresholdDays])
            )
            ->getDatabaseMessage();
    }
}
