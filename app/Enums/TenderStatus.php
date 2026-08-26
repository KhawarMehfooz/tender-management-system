<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenderStatus: string implements HasLabel
{
    case INTAKE = 'intake';
    case REVIEW = 'review';
    case DECISION = 'decision';
    case PROCESSING = 'processing';
    case QUALITY = 'quality';
    case SUBMISSION = 'submission';
    case FOLLOW_UP = 'follow-up';
    case WON = 'won';
    case LOST = 'lost';
    case CANCELLED = 'cancelled';
    case NOT_EVALUATED = 'not-evaluated';
    case EXCLUDED = 'excluded';

    public function getLabel(): string
    {
        return __('tenders.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::WON => 'success',
            self::LOST, self::EXCLUDED => 'danger',
            self::CANCELLED, self::NOT_EVALUATED => 'gray',
            default => 'warning',
        };
    }
}
