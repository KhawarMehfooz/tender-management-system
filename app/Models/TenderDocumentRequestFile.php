<?php

namespace App\Models;

use Database\Factories\TenderDocumentRequestFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $tender_document_request_id
 * @property string $uploaded_by
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_document_request_id', 'uploaded_by', 'file_path', 'original_filename', 'mime_type', 'size'])]
class TenderDocumentRequestFile extends Model
{
    /** @use HasFactory<TenderDocumentRequestFileFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<TenderDocumentRequest, $this>
     */
    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(TenderDocumentRequest::class, 'tender_document_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * A short-lived signed URL, mirroring TaskAttachment::downloadUrl() (see [[controllers]]).
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'tender-document-request-files.download',
            now()->addMinutes(5),
            ['tenderDocumentRequestFile' => $this],
        );
    }
}
