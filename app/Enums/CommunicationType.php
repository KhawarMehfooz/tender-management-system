<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommunicationType: string implements HasLabel
{
    case BIDDER_QUESTION = 'bidder-question';
    case CLARIFICATION = 'clarification';
    case AMENDMENT = 'amendment';
    case EMAIL = 'email';
    case PHONE_CALL = 'phone-call';
    case INTERNAL_COMMENT = 'internal-comment';

    public function getLabel(): string
    {
        return __('communication_types.'.$this->value);
    }
}
