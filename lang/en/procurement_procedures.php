<?php

return [
    'label' => 'Procurement procedure',
    'plural_label' => 'Procurement procedures',
    'form' => [
        'section_heading' => 'Procedure details',
        'section_description' => 'The legal procurement procedure type a tender follows. Feeds procedure-based reporting.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'active' => 'Active',
        'active_helper' => 'Inactive procedures are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
