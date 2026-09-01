<?php

namespace App\Console\Commands;

use App\Enums\Right;
use App\Models\Certificate;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\CertificateExpiringNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Reminders at 90/30/7 days before Certificate::expiry_date, plus one final notice once the
 * certificate has actually expired. last_reminder_threshold_days is forceFill-only and moves
 * only downward — 90, then 30, then 7, then EXPIRED_MARKER — mirroring [[deadlines]]'s
 * escalation_level columns: catch-up after downtime can fire several thresholds in one run,
 * but each threshold fires at most once overall.
 */
#[Signature('certificates:check-expiry')]
#[Description('Sends reminders for certificates nearing or past their expiry date to every Right::MANAGE_CERTIFICATES holder.')]
class CheckCertificateExpiry extends Command
{
    /**
     * @var list<int>
     */
    private const REMINDER_THRESHOLDS_DAYS = [90, 30, 7];

    /**
     * Marks the final "already expired" notice in last_reminder_threshold_days — distinct
     * from every real threshold above, all of which are >0.
     */
    private const EXPIRED_MARKER = 0;

    public function handle(): int
    {
        if (! Permission::where('name', Right::MANAGE_CERTIFICATES->value)->exists()) {
            return self::SUCCESS;
        }

        $recipients = User::permission(Right::MANAGE_CERTIFICATES->value)->get();

        if ($recipients->isEmpty()) {
            return self::SUCCESS;
        }

        Certificate::query()
            ->get()
            ->each(function (Certificate $certificate) use ($recipients): void {
                $daysUntilExpiry = (int) now()->startOfDay()->diffInDays($certificate->expiry_date->copy()->startOfDay(), false);
                $currentThreshold = $certificate->last_reminder_threshold_days;

                foreach (self::REMINDER_THRESHOLDS_DAYS as $thresholdDays) {
                    if ($daysUntilExpiry > $thresholdDays) {
                        continue;
                    }

                    if ($currentThreshold !== null && $currentThreshold <= $thresholdDays) {
                        continue;
                    }

                    Notification::send($recipients, new CertificateExpiringNotification($certificate, $thresholdDays));
                    $certificate->forceFill([
                        'last_reminder_threshold_days' => $thresholdDays,
                        'last_reminder_sent_at' => now(),
                    ])->save();
                    $currentThreshold = $thresholdDays;
                }

                if ($certificate->expiry_date->isPast() && ($currentThreshold === null || $currentThreshold > self::EXPIRED_MARKER)) {
                    Notification::send($recipients, new CertificateExpiringNotification($certificate, null));
                    $certificate->forceFill([
                        'last_reminder_threshold_days' => self::EXPIRED_MARKER,
                        'last_reminder_sent_at' => now(),
                    ])->save();
                }
            });

        return self::SUCCESS;
    }
}
