<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompetitorOutcome: string implements HasLabel
{
    case WE_WON = 'we-won';
    case THEY_WON = 'they-won';
    case UNKNOWN = 'unknown';

    public function getLabel(): string
    {
        return __('competitor_outcomes.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::WE_WON => 'success',
            self::THEY_WON => 'danger',
            self::UNKNOWN => 'gray',
        };
    }
}
