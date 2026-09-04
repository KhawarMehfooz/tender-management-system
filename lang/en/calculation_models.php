<?php

return [
    'deployment-hours' => 'Deployment hours',
    'area-based' => 'Area-based',
    'formulas' => [
        'deployment-hours' => [
            'cost_per_hour = wage_rate × (1 + supplements %) × (1 + social_costs %)',
            'total_cost = cost_per_hour × hours',
            'price_before_risk = total_cost × (1 + target_margin %)',
            'bid_price = price_before_risk × (1 + risk_surcharge %)',
            'unit_price = bid_price ÷ hours',
            'break_even = total_cost',
            'actual_margin % = (bid_price − total_cost) ÷ total_cost × 100',
        ],
        'area-based' => [
            'labour_cost = labour_hours × wage_rate',
            'total_cost = labour_cost + machines_consumables_cost',
            'price_before_risk = total_cost × (1 + target_margin %)',
            'bid_price = price_before_risk × (1 + risk_surcharge %)',
            'unit_price = bid_price ÷ area',
            'break_even = total_cost',
            'actual_margin % = (bid_price − total_cost) ÷ total_cost × 100',
        ],
    ],
];
