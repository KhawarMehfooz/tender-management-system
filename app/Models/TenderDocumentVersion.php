<?php

namespace App\Models;

use Database\Factories\TenderDocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $tender_document_id
 * @property int $version_number
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property string $uploaded_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'tender_document_id', 'version_number', 'file_path', 'original_filename', 'mime_type',
    'size', 'uploaded_by',
])]
class TenderDocumentVersion extends Model
{
    /** @use HasFactory<TenderDocumentVersionFactory> */
    use HasFactory, HasUuids;

    /**
     * No updated_at — versions are immutable by construction, never edited after upload.
     */
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<TenderDocument, $this>
     */
    public function tenderDocument(): BelongsTo
    {
        return $this->belongsTo(TenderDocument::class);
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
            'tender-documents.download',
            now()->addMinutes(5),
            ['tenderDocumentVersion' => $this],
        );
    }
}
