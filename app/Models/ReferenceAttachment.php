<?php

namespace App\Models;

use Database\Factories\ReferenceAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $reference_id
 * @property string $uploaded_by
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reference_id', 'uploaded_by', 'file_path', 'original_filename', 'mime_type', 'size'])]
class ReferenceAttachment extends Model
{
    /** @use HasFactory<ReferenceAttachmentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Reference, $this>
     */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(Reference::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Short-lived signed URL, mirrors TaskAttachment::downloadUrl() — see [[controllers]].
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'reference-attachments.download',
            now()->addMinutes(5),
            ['referenceAttachment' => $this],
        );
    }
}
