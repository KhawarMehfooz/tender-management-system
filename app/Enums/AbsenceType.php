<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AbsenceType: string implements HasLabel
{
    case HOLIDAY = 'holiday';
    case SICKNESS = 'sickness';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return __('absence_types.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::HOLIDAY => 'success',
            self::SICKNESS => 'danger',
            self::OTHER => 'gray',
        };
    }
}
