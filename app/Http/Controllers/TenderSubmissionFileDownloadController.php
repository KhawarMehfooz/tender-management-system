<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use App\Models\TenderSubmissionFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenderSubmissionFileDownloadController extends Controller
{
    /**
     * Stream the file to the browser. `Tender::query()` re-applies ServiceCategoryScope, so a
     * category-scoped user requesting another category's file gets a 404 here rather than a
     * reachable-but-hidden download link — mirrors TaskAttachmentDownloadController (see
     * [[controllers]]).
     */
    public function __invoke(TenderSubmissionFile $tenderSubmissionFile): StreamedResponse|Response
    {
        $submission = $tenderSubmissionFile->submission;

        Tender::query()->findOrFail($submission->tender_id);

        abort_unless(Storage::disk('local')->exists($tenderSubmissionFile->file_path), 404);

        return Storage::disk('local')->download(
            $tenderSubmissionFile->file_path,
            $tenderSubmissionFile->original_filename,
        );
    }
}
