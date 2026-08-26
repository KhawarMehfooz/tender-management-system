<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskPriority: string implements HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function getLabel(): string
    {
        return __('tasks.priority.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'info',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }
}
