<?php

namespace App\Models;

use App\Enums\BidDecision;
use App\Exceptions\BidDecisionReasonRequiredException;
use Database\Factories\TenderBidDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property BidDecision $decision
 * @property string|null $reason
 * @property int|null $score
 * @property string $decided_by
 * @property Carbon $decided_at
 */
#[Fillable(['tender_id', 'decision', 'reason', 'score', 'decided_by', 'decided_at'])]
class TenderBidDecision extends Model
{
    /** @use HasFactory<TenderBidDecisionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (TenderBidDecision $decision): void {
            if ($decision->decision === BidDecision::NO_BID && $decision->reason === null) {
                throw BidDecisionReasonRequiredException::make();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => BidDecision::class,
            'score' => 'integer',
            'decided_at' => 'datetime',
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
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
