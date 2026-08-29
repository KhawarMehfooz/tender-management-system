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
        'code' => 'Code',
        'code_helper' => 'Short unique prefix (max 4 letters) used to generate internal tender IDs for this category, e.g. SEC-2026-0001.',
        'description' => 'Description',
        'calculation_model' => 'Calculation model',
        'calculation_model_helper' => 'Determines which cost-driver fields and formula are used when calculating a bid for tenders in this category.',
        'active' => 'Active',
        'active_helper' => 'Inactive categories are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
