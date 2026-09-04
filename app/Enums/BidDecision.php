<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BidDecision: string implements HasLabel
{
    case BID = 'bid';
    case NO_BID = 'no-bid';

    public function getLabel(): string
    {
        return __('bid_decisions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::BID => 'success',
            self::NO_BID => 'danger',
        };
    }
}
