<?php

namespace App\Models;

use Database\Factories\TenderHardDeletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable audit record for a hard-deleted tender — every hard delete is
 * logged with a reason. `tender_id` intentionally carries no foreign key —
 * the referenced row is gone by the time this log is read, so
 * `internal_id`/`title` are snapshotted here for identification.
 *
 * @property string $id
 * @property string $tender_id
 * @property string $internal_id
 * @property string $title
 * @property string $deleted_by
 * @property string $reason
 * @property Carbon $deleted_at
 */
#[Fillable(['tender_id', 'internal_id', 'title', 'deleted_by', 'reason', 'deleted_at'])]
class TenderHardDeletion extends Model
{
    /** @use HasFactory<TenderHardDeletionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
