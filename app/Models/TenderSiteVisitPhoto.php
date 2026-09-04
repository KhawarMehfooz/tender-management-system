<?php

namespace App\Models;

use Database\Factories\TenderSiteVisitPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $tender_site_visit_id
 * @property string $uploaded_by
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_site_visit_id', 'uploaded_by', 'file_path', 'original_filename', 'mime_type', 'size'])]
class TenderSiteVisitPhoto extends Model
{
    /** @use HasFactory<TenderSiteVisitPhotoFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<TenderSiteVisit, $this>
     */
    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(TenderSiteVisit::class, 'tender_site_visit_id');
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
            'tender-site-visit-photos.download',
            now()->addMinutes(5),
            ['tenderSiteVisitPhoto' => $this],
        );
    }
}
