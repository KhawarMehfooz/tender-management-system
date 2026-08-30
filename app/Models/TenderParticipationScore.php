<?php

namespace App\Models;

use Database\Factories\TenderParticipationScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_id
 * @property int|null $distance_rating
 * @property int|null $staffing_requirement_rating
 * @property int|null $wage_qualification_rating
 * @property int|null $reference_position_rating
 * @property int|null $competitive_intensity_rating
 * @property int|null $contractual_penalties_rating
 * @property int|null $strategic_value_rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tender_id',
    'distance_rating',
    'staffing_requirement_rating',
    'wage_qualification_rating',
    'reference_position_rating',
    'competitive_intensity_rating',
    'contractual_penalties_rating',
    'strategic_value_rating',
])]
class TenderParticipationScore extends Model
{
    /** @use HasFactory<TenderParticipationScoreFactory> */
    use HasFactory, HasUuids;

    /**
     * The 7 manually-rated factors (idea.md's remaining participation-score factors that have
     * no other data source yet). Each is 1-5, validated in the form as well as here.
     *
     * @var list<string>
     */
    public const MANUAL_RATING_FIELDS = [
        'distance_rating',
        'staffing_requirement_rating',
        'wage_qualification_rating',
        'reference_position_rating',
        'competitive_intensity_rating',
        'contractual_penalties_rating',
        'strategic_value_rating',
    ];

    /**
     * Past win rate has no source until M9/M10 exist, so it's fixed at a neutral rating and
     * always flagged as unknown in the UI rather than silently averaged in as a real measurement.
     */
    private const PAST_WIN_RATE_RATING = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_rating' => 'integer',
            'staffing_requirement_rating' => 'integer',
            'wage_qualification_rating' => 'integer',
            'reference_position_rating' => 'integer',
            'competitive_intensity_rating' => 'integer',
            'contractual_penalties_rating' => 'integer',
            'strategic_value_rating' => 'integer',
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
     * The overall participation score (0-100): 10 factors rated 1-5, equal weight, summed
     * and scaled to a 0-100 range. Returns null ("incomplete") until all 7 manual ratings are
     * set — a partial score would misrepresent an incomplete assessment as a real measurement.
     */
    public function score(): ?int
    {
        $manualRatings = array_map(fn (string $field): ?int => $this->{$field}, self::MANUAL_RATING_FIELDS);

        if (in_array(null, $manualRatings, true)) {
            return null;
        }

        $total = array_sum($manualRatings)
            + $this->contractValueRating()
            + $this->marginRating()
            + self::PAST_WIN_RATE_RATING;

        return (int) round($total / 50 * 100);
    }

    /**
     * Past win rate is always unknown until M9/M10 exist; exposed so the UI can render the
     * same "unknown" note next to the fixed rating used in score().
     */
    public function pastWinRateRating(): int
    {
        return self::PAST_WIN_RATE_RATING;
    }

    /**
     * Bucketed 1-5 rating derived from Tender::estimated_contract_volume. An unset or
     * explicitly-unknown volume is treated the same as the lowest bucket, not skipped.
     */
    public function contractValueRating(): int
    {
        return self::bucketContractValue(
            $this->tender->estimated_contract_volume_unknown ? null : $this->tender->estimated_contract_volume,
        );
    }

    /**
     * Bucketed 1-5 rating derived from the tender's current calculation's actual_margin.
     * No calculation yet, or a non-positive margin, is treated as the lowest bucket.
     */
    public function marginRating(): int
    {
        $margin = $this->tender->currentCalculation()->first()?->actual_margin;

        return self::bucketMargin($margin === null ? null : (float) $margin);
    }

    private static function bucketContractValue(?float $volume): int
    {
        return match (true) {
            $volume === null => 1,
            $volume < 50_000 => 1,
            $volume < 150_000 => 2,
            $volume < 400_000 => 3,
            $volume < 1_000_000 => 4,
            default => 5,
        };
    }

    private static function bucketMargin(?float $marginPercent): int
    {
        return match (true) {
            $marginPercent === null => 1,
            $marginPercent <= 0 => 1,
            $marginPercent < 5 => 2,
            $marginPercent < 10 => 3,
            $marginPercent < 20 => 4,
            default => 5,
        };
    }
}
