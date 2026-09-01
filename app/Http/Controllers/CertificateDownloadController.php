<?php

namespace App\Http\Controllers;

use App\Enums\Right;
use App\Models\Certificate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController extends Controller
{
    /**
     * Stream the certificate's file to the browser. Re-checks Right::MANAGE_CERTIFICATES here
     * too, not just at the resource/table level — a leaked/guessed signed download URL bypasses
     * the table query entirely, same reasoning as TenderDocumentDownloadController's see-prices
     * re-check (see [[controllers]]).
     */
    public function __invoke(Certificate $certificate): StreamedResponse|Response
    {
        abort_unless(auth()->user()?->can(Right::MANAGE_CERTIFICATES->value), 403);

        abort_unless($certificate->file_path !== null && Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download(
            $certificate->file_path,
            $certificate->original_filename,
        );
    }
}
