<?php

namespace App\Models;

use App\Enums\CalculationApprovalStep;
use Database\Factories\TenderCalculationApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tender_calculation_id
 * @property CalculationApprovalStep $step
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $comment
 */
#[Fillable(['tender_calculation_id', 'step', 'approved_by', 'approved_at', 'comment'])]
class TenderCalculationApproval extends Model
{
    /** @use HasFactory<TenderCalculationApprovalFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => CalculationApprovalStep::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TenderCalculation, $this>
     */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(TenderCalculation::class, 'tender_calculation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
