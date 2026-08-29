<?php

namespace App\Enums;

use App\Calculations\AreaBasedCalculationEngine;
use App\Calculations\CalculationEngine;
use App\Calculations\DeploymentHoursCalculationEngine;
use Filament\Support\Contracts\HasLabel;

enum CalculationModel: string implements HasLabel
{
    case DEPLOYMENT_HOURS = 'deployment-hours';
    case AREA_BASED = 'area-based';

    public function getLabel(): string
    {
        return __('calculation_models.'.$this->value);
    }

    public function engine(): CalculationEngine
    {
        return match ($this) {
            self::DEPLOYMENT_HOURS => new DeploymentHoursCalculationEngine,
            self::AREA_BASED => new AreaBasedCalculationEngine,
        };
    }

    /**
     * The fixed formula this model's engine computes, as an ordered list of steps — a static
     * reference for the UI to explain "how" a calculation's outputs were derived, not a
     * numeric breakdown of any one calculation's actual values.
     *
     * @return array<int, string>
     */
    public function formulaSteps(): array
    {
        /** @var array<int, string> */
        return __('calculation_models.formulas.'.$this->value);
    }
}
