<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use App\Exceptions\InvalidDocumentRequestStatusTransitionException;
use Database\Factories\TenderDocumentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $tender_id
 * @property string|null $tender_communication_id
 * @property string $description
 * @property string $owner_id
 * @property Carbon|null $deadline
 * @property DocumentRequestStatus $status
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'tender_communication_id', 'description', 'owner_id', 'deadline', 'status',
    'created_by',
])]
class TenderDocumentRequest extends Model
{
    /** @use HasFactory<TenderDocumentRequestFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'status' => DocumentRequestStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Tender, $this>
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * @return BelongsTo<TenderCommunication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(TenderCommunication::class, 'tender_communication_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TenderDocumentRequestFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TenderDocumentRequestFile::class);
    }

    /**
     * @return HasMany<TenderDocumentRequestStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(TenderDocumentRequestStatusChange::class)->latest('changed_at');
    }

    /**
     * Mirrors Tender::changeStatusTo()'s shape at a smaller scale: validates the transition,
     * then writes the new status and its audit row in one transaction.
     */
    public function changeStatusTo(DocumentRequestStatus $newStatus, User $actor, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidDocumentRequestStatusTransitionException::make($this->status, $newStatus);
        }

        DB::transaction(function () use ($newStatus, $actor, $reason): void {
            $fromStatus = $this->status;
            $changedAt = now();

            $this->update(['status' => $newStatus]);

            $this->statusChanges()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by' => $actor->id,
                'reason' => $reason,
                'changed_at' => $changedAt,
            ]);
        });
    }
}
