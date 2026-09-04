<?php

namespace App\Calculations\Concerns;

use InvalidArgumentException;

trait ExtractsCostDriverInputs
{
    /**
     * @param  array<string, mixed>  $inputValues
     */
    private function requireFloat(array $inputValues, string $key): float
    {
        if (! isset($inputValues[$key]) || ! is_numeric($inputValues[$key])) {
            throw new InvalidArgumentException("Calculation input \"{$key}\" is required and must be numeric.");
        }

        return (float) $inputValues[$key];
    }
}
