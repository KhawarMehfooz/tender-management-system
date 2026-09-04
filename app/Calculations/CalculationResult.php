<?php

namespace App\Calculations;

readonly class CalculationResult
{
    public function __construct(
        public float $bidPrice,
        public float $unitPrice,
        public float $minMargin,
        public float $targetMargin,
        public float $actualMargin,
        public float $breakEven,
        public float $riskSurcharge,
    ) {}

    /**
     * @return array{bid_price: float, unit_price: float, min_margin: float, target_margin: float, actual_margin: float, break_even: float, risk_surcharge: float}
     */
    public function toOutputColumns(): array
    {
        return [
            'bid_price' => $this->bidPrice,
            'unit_price' => $this->unitPrice,
            'min_margin' => $this->minMargin,
            'target_margin' => $this->targetMargin,
            'actual_margin' => $this->actualMargin,
            'break_even' => $this->breakEven,
            'risk_surcharge' => $this->riskSurcharge,
        ];
    }
}
