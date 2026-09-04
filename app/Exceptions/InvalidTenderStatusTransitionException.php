<?php

namespace App\Exceptions;

use App\Enums\TenderStatus;
use RuntimeException;

class InvalidTenderStatusTransitionException extends RuntimeException
{
    public static function make(TenderStatus $from, TenderStatus $to): self
    {
        return new self("Tender cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }
}
