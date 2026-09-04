<?php

namespace App\Http\Controllers;

use App\Enums\Right;
use App\Models\ScheduledReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduledReportDownloadController extends Controller
{
    /**
     * A scheduled report spans every category by nature (a portfolio-wide management summary),
     * so unlike TenderDocumentDownloadController/TaskAttachmentDownloadController this gates on
     * a right rather than re-deriving a category scope. Right::VIEW_EMPLOYEE_STATISTICS matches
     * the same right the interactive "management reporting" export row and the Statistics
     * page's cross-employee figures are already gated behind (see [[milestones]]).
     */
    public function __invoke(ScheduledReport $scheduledReport): StreamedResponse
    {
        abort_unless(auth()->user()?->can(Right::VIEW_EMPLOYEE_STATISTICS->value), 403);

        abort_unless(Storage::disk('local')->exists($scheduledReport->file_path), 404);

        return Storage::disk('local')->download(
            $scheduledReport->file_path,
            $scheduledReport->report_type.'-'.$scheduledReport->period_type->value.'-report.pdf',
        );
    }
}
