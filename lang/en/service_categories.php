<?php

return [
    'label' => 'Service category',
    'plural_label' => 'Service categories',
    'form' => [
        'section_heading' => 'Category details',
        'section_description' => 'The name and description are shown wherever tenders are scoped by category.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'description' => 'Description',
        'active' => 'Active',
        'active_helper' => 'Inactive categories are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
