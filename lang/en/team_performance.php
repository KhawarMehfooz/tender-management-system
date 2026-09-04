<?php

return [
    'navigation_label' => 'Team Performance',
    'title' => 'Team Performance',
    'description' => 'Per-department task performance and approval-chain bottleneck analysis.',
    'departments' => [
        'heading' => 'Performance by department',
        'no_data' => 'No task activity recorded yet.',
        'department' => 'Department',
        'total' => 'Total tasks',
        'on_time_rate' => 'On-time rate',
        'correction_loop_rate' => 'Correction-loop rate',
        'no_rate' => '—',
    ],
    'bottleneck' => [
        'heading' => 'Approval step bottleneck analysis',
        'description' => 'Average time each approval step spends waiting, measured from the previous step\'s approval (or calculation creation for the first step).',
        'no_data' => 'No completed approvals yet.',
        'step' => 'Step',
        'sample_size' => 'Approvals counted',
        'average_duration_days' => 'Average duration (days)',
    ],
    'rankings' => [
        'heading' => 'Rankings',
        'description' => 'Weighted performance score, sorted highest first. Win rate is shown separately and does not affect the score.',
        'no_data' => 'No users to rank yet.',
        'employee' => 'Employee',
        'department' => 'Department',
        'score' => 'Score',
        'win_rate' => 'Win rate',
        'no_rate' => '—',
    ],
];
