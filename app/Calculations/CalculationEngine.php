<?php

namespace App\Calculations;

interface CalculationEngine
{
    /**
     * @param  array<string, mixed>  $inputValues  keyed by the category's cost-driver field_key
     */
    public function calculate(array $inputValues): CalculationResult;
}
