<?php

namespace App\Exceptions;

use App\Models\Tender;
use RuntimeException;

class TenderCalculationNotApprovedException extends RuntimeException
{
    public static function make(Tender $tender): self
    {
        return new self("Tender \"{$tender->id}\" cannot move to submission until its current calculation's 6-step approval chain is complete.");
    }
}
