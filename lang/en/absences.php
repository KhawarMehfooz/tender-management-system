<?php

return [
    'label' => 'Absence',
    'plural_label' => 'Absences',
    'navigation_label' => 'Absences',
    'fields' => [
        'user_id' => 'Employee',
        'type' => 'Type',
        'starts_at' => 'Starts at',
        'ends_at' => 'Ends at',
        'notes' => 'Notes',
        'cover_user_id' => 'Cover',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
    'validation' => [
        'ends_before_starts' => 'The end date cannot be before the start date.',
    ],
];
