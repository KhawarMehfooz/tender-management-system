<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TeamRole: string implements HasLabel
{
    case CALCULATION = 'calculation';
    case CONCEPT = 'concept';
    case EVIDENCE_DOCUMENTS = 'evidence-documents';
    case QUALITY_CONTROL = 'quality-control';
    case FINAL_APPROVAL = 'final-approval';
    case COMMUNICATION = 'communication';
    case COORDINATION = 'coordination';

    public function getLabel(): string
    {
        return __('team_roles.'.$this->value);
    }
}
