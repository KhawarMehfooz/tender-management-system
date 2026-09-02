<?php

return [
    'navigation_label' => 'Pipeline & Forecast',
    'title' => 'Pipeline & Forecast',
    'description' => 'Every open (non-terminal) tender with its normalized win probability and weighted pipeline value (volume x probability).',
    'win_probability_unknown' => 'Incomplete',
    'total_weighted_value' => 'Total weighted pipeline value',
    'columns' => [
        'win_probability' => 'Win probability',
        'weighted_value' => 'Weighted value',
        'resource_check' => 'Resource check',
    ],
    'resource_check_coverage' => ':covered / :total roles covered',
];
