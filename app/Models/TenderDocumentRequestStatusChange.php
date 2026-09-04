<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use Database\Factories\TenderDocumentRequestStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_document_request_id
 * @property DocumentRequestStatus|null $from_status
 * @property DocumentRequestStatus $to_status
 * @property string $changed_by
 * @property string|null $reason
 * @property Carbon $changed_at
 */
#[Fillable(['tender_document_request_id', 'from_status', 'to_status', 'changed_by', 'reason', 'changed_at'])]
class TenderDocumentRequestStatusChange extends Model
{
    /** @use HasFactory<TenderDocumentRequestStatusChangeFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => DocumentRequestStatus::class,
            'to_status' => DocumentRequestStatus::class,
            'changed_at' => 'datetime',
        ];
    }

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
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
