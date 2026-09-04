<?php

namespace App\Models;

use App\Enums\TenderStatus;
use Database\Factories\TenderStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property TenderStatus $from_status
 * @property TenderStatus $to_status
 * @property string $changed_by
 * @property string|null $reason
 * @property Carbon $changed_at
 */
#[Fillable(['tender_id', 'from_status', 'to_status', 'changed_by', 'reason', 'changed_at'])]
class TenderStatusChange extends Model
{
    /** @use HasFactory<TenderStatusChangeFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TenderStatus::class,
            'to_status' => TenderStatus::class,
            'changed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
