<?php

namespace App\Console\Commands;

use App\Enums\ReportPeriod;
use App\Enums\RoleName;
use App\Filament\Pages\Reports;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Notifications\ScheduledReportGeneratedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the management-reporting PDF (Reports::managementRows(), the same report row the
 * interactive Reports page offers, but re-run here with an explicit closed date range and
 * $includePrices forced true since there's no acting Filament user to check Right::SEE_PRICES
 * against in a console context — the download route re-gates access instead, see
 * [[milestones]]). One command, three schedule entries (routes/console.php), not three
 * commands — generation logic is identical modulo the date range.
 */
#[Signature('reports:generate-scheduled {--period=monthly : monthly, quarterly, or annual}')]
#[Description('Generates the management-reporting PDF for the closed period and notifies every super-admin/department-head.')]
class GenerateScheduledReports extends Command
{
    public function handle(): int
    {
        $period = ReportPeriod::tryFrom((string) $this->option('period'));

        if ($period === null) {
            $this->error('Invalid --period, expected one of: '.implode(', ', array_column(ReportPeriod::cases(), 'value')));

            return self::FAILURE;
        }

        [$from, $to] = $this->closedPeriodRange($period);

        $headings = ['Metric', 'Value'];
        $rows = Reports::managementRows($from, $to, true);

        $pdf = Pdf::loadView('reports.management', [
            'headings' => $headings,
            'rows' => $rows,
            'title' => __('reports.types.management.label').' — '.$period->getLabel().' ('.$from->toFormattedDateString().' – '.$to->toFormattedDateString().')',
        ]);

        $filePath = 'scheduled-reports/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($filePath, $pdf->output());

        $scheduledReport = ScheduledReport::query()->create([
            'report_type' => 'management',
            'period_type' => $period,
            'period_start' => $from,
            'period_end' => $to,
            'file_path' => $filePath,
            'generated_at' => now(),
        ]);

        $recipients = User::role([RoleName::SUPER_ADMIN->value, RoleName::DEPARTMENT_HEAD->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ScheduledReportGeneratedNotification($scheduledReport));
        }

        return self::SUCCESS;
    }

    /**
     * The most recently closed full period as of "now" — a monthly run on the 1st reports last
     * calendar month, a quarterly run reports last quarter, an annual run reports last year.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function closedPeriodRange(ReportPeriod $period): array
    {
        return match ($period) {
            ReportPeriod::MONTHLY => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            ReportPeriod::QUARTERLY => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            ReportPeriod::ANNUAL => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
        };
    }
}
