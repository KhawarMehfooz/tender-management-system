<?php

return [
    'label' => 'Calculation',
    'plural_label' => 'Calculations',
    'fields' => [
        'version_number' => 'Version',
        'created_by' => 'Created by',
        'created_at' => 'Created at',
        'bid_price' => 'Bid price',
        'unit_price' => 'Unit price',
        'min_margin' => 'Min. margin',
        'target_margin' => 'Target margin',
        'actual_margin' => 'Actual margin',
        'break_even' => 'Break-even',
        'risk_surcharge' => 'Risk surcharge',
        'comment' => 'Comment',
        'step' => 'Step',
        'status' => 'Status',
        'approved_by' => 'Approved by',
        'approved_at' => 'Approved at',
    ],
    'sections' => [
        'cost_inputs' => 'Cost inputs',
        'margin_inputs' => 'Margin & risk',
        'results' => 'Results',
        'formula' => 'How this is calculated',
        'approval_chain' => 'Approval chain',
    ],
    'infolist' => [
        'money_eur' => '€:amount',
    ],
    'approval_status' => [
        'approved' => 'Approved',
        'pending' => 'Pending',
    ],
    'actions' => [
        'new_calculation' => 'New calculation',
        'duplicate' => 'Duplicate',
        'approve_step' => 'Approve: :step',
        'approve_success' => 'Step approved',
        'no_calculation_model' => 'This tender\'s service category has no calculation model configured yet.',
    ],
];
