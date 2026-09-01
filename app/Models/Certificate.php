<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property CertificateType $type
 * @property string $name
 * @property string|null $issuing_body
 * @property Carbon $valid_from
 * @property Carbon $expiry_date
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $notes
 * @property int|null $last_reminder_threshold_days
 * @property Carbon|null $last_reminder_sent_at
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'name',
    'issuing_body',
    'valid_from',
    'expiry_date',
    'file_path',
    'original_filename',
    'mime_type',
    'size',
    'notes',
    'created_by',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasUuids;

    /**
     * A certificate is considered "expiring soon" once its expiry date is within this many
     * days — matches the lowest reminder threshold the expiry-check command fires at.
     */
    private const EXPIRING_SOON_WITHIN_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificateType::class,
            'valid_from' => 'date',
            'expiry_date' => 'date',
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Computed, not persisted — always reflects "today" rather than going stale.
     */
    public function status(): CertificateStatus
    {
        if ($this->expiry_date->isPast()) {
            return CertificateStatus::EXPIRED;
        }

        if (! $this->expiry_date->isAfter(now()->addDays(self::EXPIRING_SOON_WITHIN_DAYS))) {
            return CertificateStatus::EXPIRING_SOON;
        }

        return CertificateStatus::VALID;
    }

    /**
     * @return BelongsToMany<Tender, $this>
     */
    public function tenders(): BelongsToMany
    {
        return $this->belongsToMany(Tender::class, 'tender_certificate')->withTimestamps();
    }

    /**
     * Short-lived signed URL, mirrors TaskAttachment::downloadUrl() — see [[controllers]].
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'certificates.download',
            now()->addMinutes(5),
            ['certificate' => $this],
        );
    }
}
