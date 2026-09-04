<?php

return [
    'label' => 'NUTS code',
    'plural_label' => 'NUTS codes',
    'form' => [
        'section_heading' => 'NUTS code details',
        'section_description' => 'Nomenclature of Territorial Units for Statistics classification used for regional tender filtering and reporting.',
    ],
    'infolist' => [
        'meta_heading' => 'Record history',
    ],
    'fields' => [
        'id' => 'ID',
        'code' => 'Code',
        'label' => 'Label',
        'level' => 'Level',
        'parent' => 'Parent region',
        'active' => 'Active',
        'active_helper' => 'Inactive codes are hidden from the tender creation form, but stay visible on existing tenders and reports.',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
];
