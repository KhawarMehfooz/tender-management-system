<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CostDriverFieldType: string implements HasLabel
{
    case NUMBER = 'number';
    case DECIMAL = 'decimal';
    case TEXT = 'text';

    public function getLabel(): string
    {
        return __('cost_driver_field_types.'.$this->value);
    }
}
