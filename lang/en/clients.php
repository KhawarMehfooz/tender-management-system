<?php

return [
    'label' => 'Client',
    'plural_label' => 'Clients',
    'form' => [
        'section_heading' => 'Client details',
        'section_description' => 'A contracting authority tracked across tenders, feeding client-history and market-intelligence reporting.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'region' => 'Region',
        'notes' => 'Notes',
        'active' => 'Active',
        'active_helper' => 'Inactive clients are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
