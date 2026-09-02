<?php

namespace App\Models;

use Database\Factories\TenderFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property Carbon|null $presentation_scheduled_at
 * @property string|null $presentation_notes
 * @property string|null $negotiation_notes
 * @property Carbon|null $bid_validity_until
 * @property Carbon|null $expected_result_date
 * @property string|null $expected_result_notes
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'presentation_scheduled_at', 'presentation_notes', 'negotiation_notes',
    'bid_validity_until', 'expected_result_date', 'expected_result_notes', 'created_by',
])]
class TenderFollowUp extends Model
{
    /** @use HasFactory<TenderFollowUpFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presentation_scheduled_at' => 'datetime',
            'bid_validity_until' => 'date',
            'expected_result_date' => 'date',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
