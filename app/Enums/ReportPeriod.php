<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportPeriod: string implements HasLabel
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUAL = 'annual';

    public function getLabel(): string
    {
        return __('report_periods.'.$this->value);
    }
}
