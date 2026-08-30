<?php

return [
    'label' => 'Participation score',
    'factors' => [
        'distance_rating' => 'Distance',
        'staffing_requirement_rating' => 'Staffing requirement',
        'wage_qualification_rating' => 'Wage / qualification requirements',
        'reference_position_rating' => 'Reference position',
        'competitive_intensity_rating' => 'Competitive intensity',
        'contractual_penalties_rating' => 'Contractual penalties',
        'strategic_value_rating' => 'Strategic value',
        'contract_value' => 'Contract value',
        'expected_margin' => 'Expected margin',
        'past_win_rate' => 'Past win rate',
    ],
    'summary' => [
        'heading' => 'Participation score',
        'incomplete' => 'Incomplete — :count of 7 ratings missing',
        'unknown_note' => 'unknown — no result history yet',
    ],
    'actions' => [
        'edit_ratings' => 'Edit score inputs',
        'save_success' => 'Ratings saved',
    ],
];
