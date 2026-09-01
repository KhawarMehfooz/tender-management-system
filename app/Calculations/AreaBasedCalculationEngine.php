<?php

namespace App\Calculations;

use App\Calculations\Concerns\ExtractsCostDriverInputs;

/**
 * Cost-driver fields expected in input_values: area, labour_hours, wage_rate,
 * machines_consumables_cost, target_margin_pct, min_margin_pct, risk_surcharge_pct.
 *
 * performance_rate is a category-configurable field for this model too, but it's
 * informational only here — labour_hours is entered directly rather than derived
 * from area / performance_rate.
 */
class AreaBasedCalculationEngine implements CalculationEngine
{
    use ExtractsCostDriverInputs;

    public function calculate(array $inputValues): CalculationResult
    {
        $area = $this->requireFloat($inputValues, 'area');
        $labourHours = $this->requireFloat($inputValues, 'labour_hours');
        $wageRate = $this->requireFloat($inputValues, 'wage_rate');
        $machinesConsumablesCost = $this->requireFloat($inputValues, 'machines_consumables_cost');
        $targetMarginPct = $this->requireFloat($inputValues, 'target_margin_pct');
        $minMarginPct = $this->requireFloat($inputValues, 'min_margin_pct');
        $riskSurchargePct = $this->requireFloat($inputValues, 'risk_surcharge_pct');

        $labourCost = $labourHours * $wageRate;
        $totalCost = $labourCost + $machinesConsumablesCost;
        $breakEven = $totalCost;

        $priceBeforeRisk = $totalCost * (1 + $targetMarginPct);
        $bidPrice = $priceBeforeRisk * (1 + $riskSurchargePct);
        $riskSurcharge = $bidPrice - $priceBeforeRisk;
        $unitPrice = $area > 0.0 ? $bidPrice / $area : 0.0;
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
