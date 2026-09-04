<?php

namespace App\Models;

use App\Enums\ReportPeriod;
use Database\Factories\ScheduledReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * A generated management-reporting PDF for a closed period, produced by
 * GenerateScheduledReports. Never hard-deleted — same "keep history" precedent as every
 * other generated/uploaded-document model in this app (Certificate, TenderDocumentVersion).
 *
 * @property string $id
 * @property string $report_type
 * @property ReportPeriod $period_type
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $file_path
 * @property Carbon $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'report_type', 'period_type', 'period_start', 'period_end', 'file_path', 'generated_at',
])]
class ScheduledReport extends Model
{
    /** @use HasFactory<ScheduledReportFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_type' => ReportPeriod::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Short-lived signed URL, mirrors Certificate::downloadUrl() — see [[controllers]].
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'scheduled-reports.download',
            now()->addMinutes(5),
            ['scheduledReport' => $this],
        );
    }
}
