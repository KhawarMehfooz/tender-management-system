<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Right: string implements HasLabel
{
    case SEE_PRICES = 'see-prices';
    case SEE_MARGINS = 'see-margins';
    case SEE_COMPETITOR_DATA = 'see-competitor-data';
    case EXECUTE_FINAL_SUBMISSION = 'execute-final-submission';
    case VIEW_EMPLOYEE_STATISTICS = 'view-employee-statistics';

    public function getLabel(): string
    {
        return __('rights.'.$this->value);
    }
}
