<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DeadlineType: string implements HasLabel
{
    case BIDDER_QUESTIONS = 'bidder-questions';
    case SITE_VISIT = 'site-visit';
    case INTERNAL_CALCULATION = 'internal-calculation';
    case CONCEPT = 'concept';
    case DOCUMENT_CHECK = 'document-check';
    case APPROVAL = 'approval';
    case QUALITY_CHECK = 'quality-check';
    case UPLOAD = 'upload';
    case SUBMISSION = 'submission';
    case DOCUMENT_REQUESTS = 'document-requests';
    case PRESENTATION = 'presentation';
    case NEGOTIATION = 'negotiation';
    case BID_VALIDITY = 'bid-validity';
    case EXPECTED_DECISION = 'expected-decision';

    public function getLabel(): string
    {
        return __('deadline_types.'.$this->value);
    }
}
