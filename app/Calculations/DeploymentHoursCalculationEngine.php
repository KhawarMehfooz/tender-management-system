<?php

namespace App\Calculations;

use App\Calculations\Concerns\ExtractsCostDriverInputs;

/**
 * Cost-driver fields expected in input_values: hours, wage_rate, supplements_pct,
 * social_costs_pct, target_margin_pct, min_margin_pct, risk_surcharge_pct.
 */
class DeploymentHoursCalculationEngine implements CalculationEngine
{
    use ExtractsCostDriverInputs;

    public function calculate(array $inputValues): CalculationResult
    {
        $hours = $this->requireFloat($inputValues, 'hours');
        $wageRate = $this->requireFloat($inputValues, 'wage_rate');
        $supplementsPct = $this->requireFloat($inputValues, 'supplements_pct');
        $socialCostsPct = $this->requireFloat($inputValues, 'social_costs_pct');
        $targetMarginPct = $this->requireFloat($inputValues, 'target_margin_pct');
        $minMarginPct = $this->requireFloat($inputValues, 'min_margin_pct');
        $riskSurchargePct = $this->requireFloat($inputValues, 'risk_surcharge_pct');

        $costPerHour = $wageRate * (1 + $supplementsPct) * (1 + $socialCostsPct);
        $totalCost = $costPerHour * $hours;
        $breakEven = $totalCost;

        $priceBeforeRisk = $totalCost * (1 + $targetMarginPct);
        $bidPrice = $priceBeforeRisk * (1 + $riskSurchargePct);
        $riskSurcharge = $bidPrice - $priceBeforeRisk;
        $unitPrice = $hours > 0.0 ? $bidPrice / $hours : 0.0;
        $actualMargin = $totalCost > 0.0 ? (($bidPrice - $totalCost) / $totalCost) * 100 : 0.0;

        return new CalculationResult(
            bidPrice: $bidPrice,
            unitPrice: $unitPrice,
            minMargin: $minMarginPct * 100,
            targetMargin: $targetMarginPct * 100,
            actualMargin: $actualMargin,
            breakEven: $breakEven,
            riskSurcharge: $riskSurcharge,
        );
    }
}
