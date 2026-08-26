<?php

return [
    'label' => 'CPV code',
    'plural_label' => 'CPV codes',
    'form' => [
        'section_heading' => 'CPV code details',
        'section_description' => 'Common Procurement Vocabulary classification used for tender filtering and reporting.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'code' => 'Code',
        'label' => 'Label',
        'active' => 'Active',
        'active_helper' => 'Inactive codes are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
