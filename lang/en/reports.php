<?php

return [
    'navigation_label' => 'Reports',
    'title' => 'Reports',
    'description' => 'Export any of the reports below as PDF or Excel. Price-bearing columns are omitted for users without the "see prices" right; competitor and performance reports are only offered to users holding the matching right.',
    'actions' => [
        'export_pdf' => 'Export PDF',
        'export_excel' => 'Export Excel',
    ],
    'types' => [
        'pipeline' => [
            'label' => 'Pipeline',
            'description' => 'Every open (non-terminal) tender with its win probability and weighted pipeline value.',
        ],
        'win_loss' => [
            'label' => 'Win/loss',
            'description' => 'Every decided (won or lost) tender with its winner and recorded win/loss reasons.',
        ],
        'competitors' => [
            'label' => 'Competitors',
            'description' => 'Encounters and win/loss record against every competitor.',
        ],
        'performance' => [
            'label' => 'Employee & department performance',
            'description' => 'Per-employee ranking and per-department task performance.',
        ],
        'deadlines' => [
            'label' => 'Deadlines',
            'description' => 'Submission-deadline reliability across every recorded submission.',
        ],
        'management' => [
            'label' => 'Management reporting',
            'description' => 'Portfolio-wide headline KPIs from the Statistics page.',
        ],
    ],
];
