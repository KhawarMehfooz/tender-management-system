<?php

namespace App\Exceptions;

use App\Models\Tender;
use RuntimeException;

class TenderTasksNotCompleteException extends RuntimeException
{
    public static function make(Tender $tender): self
    {
        return new self("Tender \"{$tender->id}\" cannot move to submission while tasks are incomplete.");
    }
}
