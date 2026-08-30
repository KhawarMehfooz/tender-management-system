<?php

namespace App\Exceptions;

use InvalidArgumentException;

class BidDecisionReasonRequiredException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('A reason is required when recording a NO_BID decision.');
    }
}
