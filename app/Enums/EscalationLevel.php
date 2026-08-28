<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EscalationLevel: string implements HasLabel
{
    case ASSIGNEE = 'assignee';
    case TEAM_LEAD = 'team-lead';
    case ADMINISTRATOR = 'administrator';
    case MANAGEMENT = 'management';

    public function getLabel(): string
    {
        return __('escalation_levels.'.$this->value);
    }

    public function level(): int
    {
        return match ($this) {
            self::ASSIGNEE => 1,
            self::TEAM_LEAD => 2,
            self::ADMINISTRATOR => 3,
            self::MANAGEMENT => 4,
        };
    }
}
