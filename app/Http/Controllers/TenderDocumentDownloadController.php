<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Enums\Right;
use App\Models\Tender;
use App\Models\TenderDocumentVersion;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenderDocumentDownloadController extends Controller
{
    /**
     * Stream the version's file to the browser. Tender::query() re-applies
     * ServiceCategoryScope, so a category-scoped user requesting another category's document
     * gets a 404 here rather than a reachable-but-hidden download link — same pattern as
     * TaskAttachmentDownloadController (see [[controllers]]). A CALCULATION-category document
     * is additionally gated on the see-prices right, mirroring
     * DocumentsRelationManager's table-query gating.
     */
    public function __invoke(TenderDocumentVersion $tenderDocumentVersion): StreamedResponse|Response
    {
        $document = $tenderDocumentVersion->tenderDocument;

        Tender::query()->findOrFail($document->tender_id);

        abort_unless(
            $document->category !== DocumentCategory::CALCULATION || auth()->user()?->can(Right::SEE_PRICES->value),
            403,
        );

        abort_unless(Storage::disk('local')->exists($tenderDocumentVersion->file_path), 404);

        return Storage::disk('local')->download(
            $tenderDocumentVersion->file_path,
            $tenderDocumentVersion->original_filename,
        );
    }
}
