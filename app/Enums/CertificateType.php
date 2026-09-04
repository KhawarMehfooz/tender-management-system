<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CertificateType: string implements HasLabel
{
    case INSURANCE = 'insurance';
    case ISO_CERTIFICATE = 'iso-certificate';
    case TRADE_REGISTRATION = 'trade-registration';
    case SECTOR_LICENCE = 'sector-licence';
    case TAX_CLEARANCE = 'tax-clearance';
    case WAGE_LABOUR_COMPLIANCE = 'wage-labour-compliance';
    case PREQUALIFICATION = 'prequalification';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return __('certificate_types.'.$this->value);
    }
}
