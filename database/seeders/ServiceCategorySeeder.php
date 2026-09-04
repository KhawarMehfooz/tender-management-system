<?php

namespace Database\Seeders;

use App\Enums\CalculationModel;
use App\Enums\CostDriverFieldType;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * The 3 standard cost-driver inputs every calculation model needs (see [[milestones]]'s
     * m5-calculation-approvals.md — these are per-calculation inputs, not ServiceCategory
     * columns, but every category using either engine needs them configured as fields).
     *
     * @var array<int, array{field_key: string, label: string, type: CostDriverFieldType, unit: ?string}>
     */
    private const array MARGIN_FIELDS = [
        ['field_key' => 'target_margin_pct', 'label' => 'Target margin', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '%'],
        ['field_key' => 'min_margin_pct', 'label' => 'Minimum margin', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '%'],
        ['field_key' => 'risk_surcharge_pct', 'label' => 'Risk surcharge', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '%'],
    ];

    /**
     * @var array<int, array{field_key: string, label: string, type: CostDriverFieldType, unit: ?string}>
     */
    private const array DEPLOYMENT_HOURS_FIELDS = [
        ['field_key' => 'hours', 'label' => 'Deployment hours', 'type' => CostDriverFieldType::NUMBER, 'unit' => 'h'],
        ['field_key' => 'wage_rate', 'label' => 'Wage rate', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '€/h'],
        ['field_key' => 'supplements_pct', 'label' => 'Supplements', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '%'],
        ['field_key' => 'social_costs_pct', 'label' => 'Social costs', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '%'],
    ];

    /**
     * @var array<int, array{field_key: string, label: string, type: CostDriverFieldType, unit: ?string}>
     */
    private const array AREA_BASED_FIELDS = [
        ['field_key' => 'area', 'label' => 'Area', 'type' => CostDriverFieldType::NUMBER, 'unit' => 'm²'],
        ['field_key' => 'labour_hours', 'label' => 'Labour hours', 'type' => CostDriverFieldType::NUMBER, 'unit' => 'h'],
        ['field_key' => 'wage_rate', 'label' => 'Wage rate', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '€/h'],
        ['field_key' => 'machines_consumables_cost', 'label' => 'Machines & consumables cost', 'type' => CostDriverFieldType::DECIMAL, 'unit' => '€'],
        ['field_key' => 'performance_rate', 'label' => 'Performance rate', 'type' => CostDriverFieldType::DECIMAL, 'unit' => 'm²/h', 'required' => false],
    ];

    /**
     * Seed a starter set of example service categories, each configured with a calculation
     * model and its matching cost-driver fields so a demo/local install can create working
     * calculations out of the box.
     *
     * These are example data for local development, not a fixed list — admins
     * can add, rename, or deactivate categories at any time.
     */
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Security Services',
                'code' => 'SEC',
                'description' => 'Guarding, access control, and site security tenders.',
                'active' => true,
                'calculation_model' => CalculationModel::DEPLOYMENT_HOURS,
            ],
            [
                'name' => 'Cleaning Services',
                'code' => 'CLN',
                'description' => 'Facility and industrial cleaning tenders.',
                'active' => true,
                'calculation_model' => CalculationModel::DEPLOYMENT_HOURS,
            ],
            [
                'name' => 'Facility Management',
                'code' => 'FM',
                'description' => 'Combined building and grounds maintenance tenders.',
                'active' => true,
                'calculation_model' => CalculationModel::AREA_BASED,
            ],
        ] as $category) {
            $model = $category['calculation_model'];
            $record = ServiceCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                $category,
            );

            $modelFields = $model === CalculationModel::DEPLOYMENT_HOURS ? self::DEPLOYMENT_HOURS_FIELDS : self::AREA_BASED_FIELDS;

            foreach ([...$modelFields, ...self::MARGIN_FIELDS] as $order => $field) {
                $record->costDriverFields()->updateOrCreate(
                    ['field_key' => $field['field_key']],
                    [
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'unit' => $field['unit'],
                        'required' => $field['required'] ?? true,
                        'order' => $order,
                    ],
                );
            }
        }
    }
}
