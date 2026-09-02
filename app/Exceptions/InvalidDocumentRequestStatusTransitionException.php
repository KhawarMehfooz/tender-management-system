<?php

namespace App\Exceptions;

use App\Enums\DocumentRequestStatus;
use RuntimeException;

class InvalidDocumentRequestStatusTransitionException extends RuntimeException
{
    public static function make(DocumentRequestStatus $from, DocumentRequestStatus $to): self
    {
        return new self("Document request cannot transition from \"{$from->value}\" to \"{$to->value}\".");
    }
}
