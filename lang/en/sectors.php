<?php

return [
    'label' => 'Sector',
    'plural_label' => 'Sectors',
    'form' => [
        'section_heading' => 'Sector details',
        'section_description' => 'The client industry a tender belongs to. Feeds sector-based market analysis reporting.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'active' => 'Active',
        'active_helper' => 'Inactive sectors are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
