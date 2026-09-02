<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WinLossReason: string implements HasLabel
{
    case PRICE = 'price';
    case QUALITY = 'quality';
    case CONCEPT = 'concept';
    case REFERENCES = 'references';
    case EXPERIENCE = 'experience';
    case STAFFING = 'staffing';
    case FORMAL_ERROR = 'formal-error';
    case EXCLUSION = 'exclusion';
    case CAPACITY = 'capacity';
    case CONTRACT_TERMS = 'contract-terms';
    case COMPETITOR = 'competitor';
    case STRATEGIC_DECISION = 'strategic-decision';

    public function getLabel(): string
    {
        return __('win_loss_reasons.'.$this->value);
    }
}
