<?php

namespace App\Models;

use App\Enums\WinLossReason;
use Database\Factories\TenderResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property string|null $winner
 * @property int|null $our_rank
 * @property float|null $winning_price
 * @property float|null $our_price
 * @property float|null $price_gap
 * @property Carbon|null $award_date
 * @property string|null $known_evaluation
 * @property string|null $reasoning
 * @property string|null $award_decision
 * @property list<WinLossReason>|null $win_loss_reasons
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id', 'winner', 'our_rank', 'winning_price', 'our_price', 'price_gap', 'award_date',
    'known_evaluation', 'reasoning', 'award_decision', 'win_loss_reasons', 'created_by',
])]
class TenderResult extends Model
{
    /** @use HasFactory<TenderResultFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_date' => 'date',
            'win_loss_reasons' => 'array',
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
