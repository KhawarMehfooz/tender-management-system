<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SkillProficiency: string implements HasLabel
{
    case NOVICE = 'novice';
    case COMPETENT = 'competent';
    case EXPERT = 'expert';

    public function getLabel(): string
    {
        return __('skill_proficiencies.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NOVICE => 'gray',
            self::COMPETENT => 'info',
            self::EXPERT => 'success',
        };
    }
}
