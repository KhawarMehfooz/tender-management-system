<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use App\Models\TenderSiteVisitPhoto;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenderSiteVisitPhotoDownloadController extends Controller
{
    /**
     * Stream the photo's file to the browser. `Tender::query()` re-applies
     * ServiceCategoryScope, so a category-scoped user requesting another category's photo
     * gets a 404 here rather than a reachable-but-hidden download link — mirrors
     * TaskAttachmentDownloadController (see [[controllers]]).
     */
    public function __invoke(TenderSiteVisitPhoto $tenderSiteVisitPhoto): StreamedResponse|Response
    {
        $siteVisit = $tenderSiteVisitPhoto->siteVisit;

        Tender::query()->findOrFail($siteVisit->tender_id);

        abort_unless(Storage::disk('local')->exists($tenderSiteVisitPhoto->file_path), 404);

        return Storage::disk('local')->download(
            $tenderSiteVisitPhoto->file_path,
            $tenderSiteVisitPhoto->original_filename,
        );
    }
}
