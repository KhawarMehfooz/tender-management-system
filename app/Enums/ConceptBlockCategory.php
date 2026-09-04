<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConceptBlockCategory: string implements HasLabel
{
    case QUALITY_MANAGEMENT = 'quality-management';
    case STAFFING_CONCEPT = 'staffing-concept';
    case COVER_ARRANGEMENTS = 'cover-arrangements';
    case ESCALATION = 'escalation';
    case COMPLAINTS = 'complaints';
    case SUSTAINABILITY = 'sustainability';
    case TRAINING = 'training';
    case DEPLOYMENT_ORGANISATION = 'deployment-organisation';
    case CATEGORY_SPECIFIC = 'category-specific';

    public function getLabel(): string
    {
        return __('concept_block_categories.'.$this->value);
    }
}
