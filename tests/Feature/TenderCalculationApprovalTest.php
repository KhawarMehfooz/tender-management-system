<?php

use App\Enums\CalculationApprovalStep;
use App\Enums\CalculationModel;
use App\Enums\Right;
use App\Enums\TeamRole;
use App\Exceptions\CalculationApprovalStepOutOfOrderException;
use App\Models\ServiceCategory;
use App\Models\Tender;
use App\Models\TenderCalculation;
use App\Models\TenderTeamMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

function teamMemberFor(Tender $tender, TeamRole $role): User
{
    $user = User::factory()->create();
    TenderTeamMember::factory()->create([
        'tender_id' => $tender->id,
        'user_id' => $user->id,
        'functional_role' => $role,
    ]);

    return $user;
}

describe('sequential enforcement', function () {
    it('rejects approving a step before its predecessor is approved', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);
        $conceptChecker = teamMemberFor($tender, TeamRole::CONCEPT);

        expect(fn () => $calculation->approve(CalculationApprovalStep::CONCEPT_CHECKED, $conceptChecker))
            ->toThrow(CalculationApprovalStepOutOfOrderException::class);
    });

    it('allows the next step once its predecessor is approved', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);
        $calculationChecker = teamMemberFor($tender, TeamRole::CALCULATION);
        $conceptChecker = teamMemberFor($tender, TeamRole::CONCEPT);

        $calculation->approve(CalculationApprovalStep::CALCULATION_CHECKED, $calculationChecker);
        $calculation->approve(CalculationApprovalStep::CONCEPT_CHECKED, $conceptChecker);

        expect($calculation->approvals()->whereNotNull('approved_at')->count())->toBe(2);
    });
});

describe('rights gating per step', function () {
    it('rejects a user without the matching team role', function () {
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);
        $wrongRole = teamMemberFor($tender, TeamRole::CONCEPT);

        expect(fn () => $calculation->approve(CalculationApprovalStep::CALCULATION_CHECKED, $wrongRole))
            ->toThrow(HttpException::class);
    });

    it('rejects a user without Right::EXECUTE_FINAL_SUBMISSION for the final step', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

        foreach (CalculationApprovalStep::cases() as $step) {
            if ($step === CalculationApprovalStep::FINAL_SUBMISSION_RELEASED) {
                continue;
            }

            $calculation->approve($step, teamMemberFor($tender, $step->teamRole()));
        }

        $unprivileged = User::factory()->create();

        expect(fn () => $calculation->approve(CalculationApprovalStep::FINAL_SUBMISSION_RELEASED, $unprivileged))
            ->toThrow(HttpException::class);
    });

    it('allows a user with Right::EXECUTE_FINAL_SUBMISSION to approve the final step', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

        foreach (CalculationApprovalStep::cases() as $step) {
            if ($step === CalculationApprovalStep::FINAL_SUBMISSION_RELEASED) {
                continue;
            }

            $calculation->approve($step, teamMemberFor($tender, $step->teamRole()));
        }

        $submitter = User::factory()->create();
        $submitter->givePermissionTo(Right::EXECUTE_FINAL_SUBMISSION->value);

        $calculation->approve(CalculationApprovalStep::FINAL_SUBMISSION_RELEASED, $submitter);

        expect($calculation->isFullyApproved())->toBeTrue();
    });
});

describe('below-minimum margin', function () {
    it('still blocks the final step until management approval exists, even for a user who could otherwise submit', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $category = ServiceCategory::factory()->create(['calculation_model' => CalculationModel::DEPLOYMENT_HOURS]);
        $tender = Tender::factory()->create(['service_category_id' => $category->id]);
        $calculation = TenderCalculation::factory()->create([
            'tender_id' => $tender->id,
            'input_values' => [
                'hours' => 50,
                'wage_rate' => 25,
                'supplements_pct' => 0,
                'social_costs_pct' => 0,
                'target_margin_pct' => 0.05,
                'min_margin_pct' => 0.1,
                'risk_surcharge_pct' => 0.02,
            ],
        ]);
        $calculation->computeOutputs();

        expect((float) $calculation->fresh()->actual_margin)->toBeLessThan((float) $calculation->fresh()->min_margin);

        foreach ([
            CalculationApprovalStep::CALCULATION_CHECKED,
            CalculationApprovalStep::CONCEPT_CHECKED,
            CalculationApprovalStep::EVIDENCE_DOCUMENTS_CHECKED,
            CalculationApprovalStep::FORMAL_REVIEW_COMPLETE,
        ] as $step) {
            $calculation->approve($step, teamMemberFor($tender, $step->teamRole()));
        }

        $submitter = User::factory()->create();
        $submitter->givePermissionTo(Right::EXECUTE_FINAL_SUBMISSION->value);

        expect(fn () => $calculation->approve(CalculationApprovalStep::FINAL_SUBMISSION_RELEASED, $submitter))
            ->toThrow(CalculationApprovalStepOutOfOrderException::class);
    });
});

describe('full chain completion', function () {
    it('completes all 6 steps in order and reports isFullyApproved', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tender = Tender::factory()->create();
        $calculation = TenderCalculation::factory()->create(['tender_id' => $tender->id]);

        expect($calculation->isFullyApproved())->toBeFalse();

        foreach (CalculationApprovalStep::cases() as $step) {
            $actor = $step->teamRole() !== null
                ? teamMemberFor($tender, $step->teamRole())
                : tap(User::factory()->create())->givePermissionTo(Right::EXECUTE_FINAL_SUBMISSION->value);

            $calculation->approve($step, $actor, "Approved {$step->value}");
        }

        expect($calculation->isFullyApproved())->toBeTrue();
        expect($calculation->approvals()->whereNotNull('comment')->count())->toBe(6);
    });
});
