<?php

namespace App\Models;

use App\Enums\CompetitorOutcome;
use Database\Factories\TenderCompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property string $competitor_id
 * @property CompetitorOutcome $outcome
 * @property string|null $known_price
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_id', 'competitor_id', 'outcome', 'known_price', 'notes'])]
class TenderCompetitor extends Model
{
    /** @use HasFactory<TenderCompetitorFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => CompetitorOutcome::class,
            'known_price' => 'decimal:2',
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
     * @return BelongsTo<Competitor, $this>
     */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
