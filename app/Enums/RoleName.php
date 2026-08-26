<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoleName: string implements HasLabel
{
    case SUPER_ADMIN = 'super-admin';
    case DEPARTMENT_HEAD = 'department-head';
    case TEAM_LEAD = 'team-lead';
    case CALCULATION = 'calculation';
    case CONCEPT_WRITER = 'concept-writer';
    case DOCUMENTATION = 'documentation';
    case QUALITY_CONTROL = 'quality-control';
    case STAFF = 'staff';
    case VIEWER = 'viewer';

    public function getLabel(): string
    {
        return __('roles.'.$this->value);
    }
}
