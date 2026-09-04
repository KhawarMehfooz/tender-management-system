<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SkillCategory: string implements HasLabel
{
    case TECHNICAL = 'technical';
    case COMPLIANCE = 'compliance';
    case LANGUAGE = 'language';
    case SOFT_SKILLS = 'soft-skills';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return __('skill_categories.'.$this->value);
    }
}
