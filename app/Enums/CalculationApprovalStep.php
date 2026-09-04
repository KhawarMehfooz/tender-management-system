<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculationApprovalStep: string implements HasLabel
{
    case CALCULATION_CHECKED = 'calculation-checked';
    case CONCEPT_CHECKED = 'concept-checked';
    case EVIDENCE_DOCUMENTS_CHECKED = 'evidence-documents-checked';
    case FORMAL_REVIEW_COMPLETE = 'formal-review-complete';
    case MANAGEMENT_APPROVED = 'management-approved';
    case FINAL_SUBMISSION_RELEASED = 'final-submission-released';

    public function getLabel(): string
    {
        return __('calculation_approval_steps.'.$this->value);
    }

    /**
     * The tender_team_members functional role gating this step, or null for
     * FINAL_SUBMISSION_RELEASED, which is gated by Right::EXECUTE_FINAL_SUBMISSION instead.
     */
    public function teamRole(): ?TeamRole
    {
        return match ($this) {
            self::CALCULATION_CHECKED => TeamRole::CALCULATION,
            self::CONCEPT_CHECKED => TeamRole::CONCEPT,
            self::EVIDENCE_DOCUMENTS_CHECKED => TeamRole::EVIDENCE_DOCUMENTS,
            self::FORMAL_REVIEW_COMPLETE => TeamRole::QUALITY_CONTROL,
            self::MANAGEMENT_APPROVED => TeamRole::FINAL_APPROVAL,
            self::FINAL_SUBMISSION_RELEASED => null,
        };
    }

    /**
     * The steps that must already be approved before this one, in chain order.
     *
     * @return list<self>
     */
    public function stepsBefore(): array
    {
        $stepsBefore = [];

        foreach (self::cases() as $case) {
            if ($case === $this) {
                break;
            }

            $stepsBefore[] = $case;
        }

        return $stepsBefore;
    }
}
