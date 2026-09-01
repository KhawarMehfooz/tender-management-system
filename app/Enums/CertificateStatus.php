<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CertificateStatus: string implements HasLabel
{
    case VALID = 'valid';
    case EXPIRING_SOON = 'expiring-soon';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return __('certificate_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::VALID => 'success',
            self::EXPIRING_SOON => 'warning',
            self::EXPIRED => 'danger',
        };
    }
}
