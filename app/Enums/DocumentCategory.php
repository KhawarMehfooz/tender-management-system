<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentCategory: string implements HasLabel
{
    case TENDER_DOCUMENTS = 'tender-documents';
    case CALCULATION = 'calculation';
    case CONCEPTS = 'concepts';
    case EVIDENCE_DOCUMENTS = 'evidence-documents';
    case REFERENCES = 'references';
    case BIDDER_QUESTIONS = 'bidder-questions';
    case COMMUNICATION = 'communication';
    case SITE_VISIT = 'site-visit';
    case FINAL_BID_DOCUMENTS = 'final-bid-documents';
    case RESULT = 'result';
    case POST_ANALYSIS = 'post-analysis';

    public function getLabel(): string
    {
        return __('document_categories.'.$this->value);
    }
}
