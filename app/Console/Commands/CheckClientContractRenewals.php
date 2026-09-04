<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Tender;
use App\Models\User;
use App\Notifications\ClientContractRenewalReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Reminders at 12/9/6 months before Tender::contract_end_date, one per threshold, tracked via
 * the 3 independent reminder_*_sent_at columns (per [[deadlines]]'s forceFill-only,
 * one-directional escalation-state pattern). Scans every tender with a contract_end_date
 * regardless of status — explicitly including lost tenders, per idea.md's client-history spec.
 * Recipients are the tender's owner plus any TEAM_LEAD/DEPARTMENT_HEAD in the tender's
 * service_category.
 */
#[Signature('tenders:check-client-renewals')]
#[Description("Sends reminders at 12/9/6 months before a tender's known contract end date, including lost tenders.")]
class CheckClientContractRenewals extends Command
{
    /**
     * @var list<int>
     */
    private const REMINDER_THRESHOLDS_MONTHS = [12, 9, 6];

    public function handle(): int
    {
        Tender::query()
            ->whereNotNull('contract_end_date')
            ->with(['owner', 'serviceCategory'])
            ->get()
            ->each(function (Tender $tender): void {
                $monthsUntilEnd = now()->startOfDay()->diffInMonths($tender->contract_end_date->copy()->startOfDay(), false);

                foreach (self::REMINDER_THRESHOLDS_MONTHS as $thresholdMonths) {
                    $column = "reminder_{$thresholdMonths}_months_sent_at";

                    if ($tender->{$column} !== null || $monthsUntilEnd > $thresholdMonths) {
                        continue;
                    }

                    $recipients = $this->recipientsFor($tender);

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new ClientContractRenewalReminderNotification($tender, $thresholdMonths));
                    }

                    $tender->forceFill([$column => now()])->save();
                }
            });

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Tender $tender): Collection
    {
        $recipients = collect([$tender->owner]);

        if (Role::whereIn('name', [RoleName::TEAM_LEAD->value, RoleName::DEPARTMENT_HEAD->value])->exists()) {
            $recipients = $recipients->merge(
                User::role([RoleName::TEAM_LEAD->value, RoleName::DEPARTMENT_HEAD->value])
                    ->where('service_category_id', $tender->service_category_id)
                    ->get(),
            );
        }

        return $recipients->filter()->unique('id');
    }
}
