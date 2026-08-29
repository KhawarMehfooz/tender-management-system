<?php

namespace App\Models;

use App\Enums\CalculationApprovalStep;
use App\Enums\Right;
use App\Exceptions\CalculationApprovalStepOutOfOrderException;
use Database\Factories\TenderCalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property string $id
 * @property string $tender_id
 * @property int $version_number
 * @property string $created_by
 * @property array<string, mixed> $input_values
 * @property string|null $bid_price
 * @property string|null $unit_price
 * @property string|null $min_margin
 * @property string|null $target_margin
 * @property string|null $actual_margin
 * @property string|null $break_even
 * @property string|null $risk_surcharge
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tender_id', 'version_number', 'created_by', 'input_values', 'bid_price', 'unit_price', 'min_margin', 'target_margin', 'actual_margin', 'break_even', 'risk_surcharge'])]
class TenderCalculation extends Model
{
    /** @use HasFactory<TenderCalculationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'input_values' => 'array',
            'bid_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'min_margin' => 'decimal:2',
            'target_margin' => 'decimal:2',
            'actual_margin' => 'decimal:2',
            'break_even' => 'decimal:2',
            'risk_surcharge' => 'decimal:2',
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

    /**
     * @return HasMany<TenderCalculationApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(TenderCalculationApproval::class);
    }

    /**
     * Runs input_values through the tender's service category's configured calculation model
     * and persists the resulting output columns.
     */
    public function computeOutputs(): void
    {
        $category = $this->tender->serviceCategory;

        if ($category->calculation_model === null) {
            throw new RuntimeException(
                "Service category \"{$category->name}\" has no calculation model configured."
            );
        }

        $result = $category->calculation_model->engine()->calculate($this->input_values);

        $this->fill($result->toOutputColumns());
        $this->save();
    }

    /**
     * Approves one step of the 6-step chain (idea.md M5). Steps must be approved in order —
     * a step cannot be approved while any earlier step in CalculationApprovalStep's declared
     * order is still unapproved, regardless of the acting user's rights for later steps.
     * Gated by the matching tender_team_members functional role for every step except
     * FINAL_SUBMISSION_RELEASED, which is gated by Right::EXECUTE_FINAL_SUBMISSION instead.
     */
    public function approve(CalculationApprovalStep $step, User $actor, ?string $comment = null): void
    {
        $teamRole = $step->teamRole();

        if ($teamRole !== null) {
            abort_unless(
                $this->tender->teamMembers()->where('user_id', $actor->id)->where('functional_role', $teamRole)->exists(),
                403,
            );
        } else {
            abort_unless($actor->can(Right::EXECUTE_FINAL_SUBMISSION->value), 403);
        }

        $approvedSteps = $this->approvals()->whereNotNull('approved_at')->pluck('step')->all();
        $missingPriorSteps = array_filter(
            $step->stepsBefore(),
            fn (CalculationApprovalStep $prior): bool => ! in_array($prior, $approvedSteps, true),
        );

        if ($missingPriorSteps !== []) {
            throw CalculationApprovalStepOutOfOrderException::make($step);
        }

        $this->approvals()->updateOrCreate(
            ['step' => $step],
            ['approved_by' => $actor->id, 'approved_at' => now(), 'comment' => $comment],
        );
    }

    /**
     * Whether every step in the 6-step chain has been approved.
     */
    public function isFullyApproved(): bool
    {
        return $this->approvals()->whereNotNull('approved_at')->count() === count(CalculationApprovalStep::cases());
    }

    /**
     * All 6 steps in chain order, each paired with its approval row if that step has been
     * approved yet — used to render the full timeline regardless of how far the chain has
     * progressed, since unapproved steps have no row in `approvals` at all.
     *
     * @return array<int, array{step: CalculationApprovalStep, approval: TenderCalculationApproval|null}>
     */
    public function approvalTimeline(): array
    {
        $approvals = $this->approvals->keyBy(fn (TenderCalculationApproval $approval): string => $approval->step->value);

        return collect(CalculationApprovalStep::cases())
            ->map(fn (CalculationApprovalStep $step): array => [
                'step' => $step,
                'approval' => $approvals->get($step->value),
            ])
            ->all();
    }
}
