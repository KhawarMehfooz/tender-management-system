<?php

return [
    'label' => 'Source',
    'plural_label' => 'Sources',
    'form' => [
        'section_heading' => 'Source details',
        'section_description' => 'Where a tender lead originated from. Feeds win-rate and volume reporting by source.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'active' => 'Active',
        'active_helper' => 'Inactive sources are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
