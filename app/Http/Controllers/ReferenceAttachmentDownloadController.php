<?php

namespace App\Http\Controllers;

use App\Models\ReferenceAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferenceAttachmentDownloadController extends Controller
{
    /**
     * Stream the attachment's file to the browser. References are a global library, not
     * tender-scoped, so unlike TenderDocumentDownloadController/TaskAttachmentDownloadController
     * there is no category scope to re-derive here — auth (route middleware) + the signed URL
     * (see [[controllers]]) are the only checks needed.
     */
    public function __invoke(ReferenceAttachment $referenceAttachment): StreamedResponse|Response
    {
        abort_unless(Storage::disk('local')->exists($referenceAttachment->file_path), 404);

        return Storage::disk('local')->download(
            $referenceAttachment->file_path,
            $referenceAttachment->original_filename,
        );
    }
}
