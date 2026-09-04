<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use App\Models\TenderDocumentRequestFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenderDocumentRequestFileDownloadController extends Controller
{
    /**
     * Stream the file to the browser. `Tender::query()` re-applies ServiceCategoryScope, so a
     * category-scoped user requesting another category's file gets a 404 here rather than a
     * reachable-but-hidden download link — mirrors TenderSiteVisitPhotoDownloadController (see
     * [[controllers]]).
     */
    public function __invoke(TenderDocumentRequestFile $tenderDocumentRequestFile): StreamedResponse|Response
    {
        $documentRequest = $tenderDocumentRequestFile->documentRequest;

        Tender::query()->findOrFail($documentRequest->tender_id);

        abort_unless(Storage::disk('local')->exists($tenderDocumentRequestFile->file_path), 404);

        return Storage::disk('local')->download(
            $tenderDocumentRequestFile->file_path,
            $tenderDocumentRequestFile->original_filename,
        );
    }
}
