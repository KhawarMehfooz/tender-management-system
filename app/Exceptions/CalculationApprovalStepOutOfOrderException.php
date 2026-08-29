<?php

namespace App\Exceptions;

use App\Enums\CalculationApprovalStep;
use RuntimeException;

class CalculationApprovalStepOutOfOrderException extends RuntimeException
{
    public static function make(CalculationApprovalStep $step): self
    {
        return new self("Calculation approval step \"{$step->value}\" cannot be approved before earlier steps in the chain.");
    }
}
