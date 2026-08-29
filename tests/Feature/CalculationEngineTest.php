<?php

use App\Calculations\AreaBasedCalculationEngine;
use App\Calculations\DeploymentHoursCalculationEngine;
use App\Enums\CalculationModel;

describe('deployment hours model', function () {
    it('computes the shared output shape from a known fixture', function () {
        $result = (new DeploymentHoursCalculationEngine)->calculate([
            'hours' => 100,
            'wage_rate' => 20,
            'supplements_pct' => 0.1,
            'social_costs_pct' => 0.2,
            'target_margin_pct' => 0.15,
            'min_margin_pct' => 0.1,
            'risk_surcharge_pct' => 0.05,
        ]);

        expect($result->breakEven)->toEqualWithDelta(2640.0, 0.01);
        expect($result->bidPrice)->toEqualWithDelta(3187.8, 0.01);
        expect($result->unitPrice)->toEqualWithDelta(31.878, 0.001);
        expect($result->riskSurcharge)->toEqualWithDelta(151.8, 0.01);
        expect($result->targetMargin)->toEqualWithDelta(15.0, 0.01);
        expect($result->minMargin)->toEqualWithDelta(10.0, 0.01);
        expect($result->actualMargin)->toEqualWithDelta(20.75, 0.01);
    });

    it('is resolved from CalculationModel::DEPLOYMENT_HOURS', function () {
        expect(CalculationModel::DEPLOYMENT_HOURS->engine())->toBeInstanceOf(DeploymentHoursCalculationEngine::class);
    });

    it('throws when a required cost-driver input is missing', function () {
        (new DeploymentHoursCalculationEngine)->calculate(['hours' => 100]);
    })->throws(InvalidArgumentException::class);
});

describe('area based model', function () {
    it('computes the shared output shape from a known fixture', function () {
        $result = (new AreaBasedCalculationEngine)->calculate([
            'area' => 500,
            'labour_hours' => 80,
            'wage_rate' => 18,
            'machines_consumables_cost' => 300,
            'target_margin_pct' => 0.12,
            'min_margin_pct' => 0.08,
            'risk_surcharge_pct' => 0.04,
        ]);

        expect($result->breakEven)->toEqualWithDelta(1740.0, 0.01);
        expect($result->bidPrice)->toEqualWithDelta(2026.752, 0.01);
        expect($result->unitPrice)->toEqualWithDelta(4.053504, 0.001);
        expect($result->riskSurcharge)->toEqualWithDelta(77.952, 0.01);
        expect($result->targetMargin)->toEqualWithDelta(12.0, 0.01);
        expect($result->minMargin)->toEqualWithDelta(8.0, 0.01);
        expect($result->actualMargin)->toEqualWithDelta(16.48, 0.01);
    });

    it('is resolved from CalculationModel::AREA_BASED', function () {
        expect(CalculationModel::AREA_BASED->engine())->toBeInstanceOf(AreaBasedCalculationEngine::class);
    });
});

describe('formula reference', function () {
    it('has a non-empty formula step list for every calculation model', function () {
        foreach (CalculationModel::cases() as $model) {
            expect($model->formulaSteps())->toBeArray()->not->toBeEmpty();
        }
    });
});

describe('below-minimum margin', function () {
    it('produces an actual_margin below min_margin when target_margin_pct undercuts it', function () {
        $result = (new DeploymentHoursCalculationEngine)->calculate([
            'hours' => 50,
            'wage_rate' => 25,
            'supplements_pct' => 0,
            'social_costs_pct' => 0,
            'target_margin_pct' => 0.05,
            'min_margin_pct' => 0.1,
            'risk_surcharge_pct' => 0.02,
        ]);

        expect($result->actualMargin)->toEqualWithDelta(7.1, 0.01);
        expect($result->minMargin)->toEqualWithDelta(10.0, 0.01);
        expect($result->actualMargin)->toBeLessThan($result->minMargin);
    });
});
