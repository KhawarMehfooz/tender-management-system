<?php

return [
    'label' => 'User',
    'plural_label' => 'Users',
    'form' => [
        'account_section_heading' => 'Account',
        'account_section_description' => 'Login credentials for this user.',
        'access_section_heading' => 'Access',
        'access_section_description' => 'Role controls navigation and menu scope. Rights grant data access independent of role.',
    ],
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_helper' => 'Leave blank to keep the current password.',
        'role' => 'Role',
        'role_last_super_admin_helper' => 'This user is the only super admin. Changing their role away from super admin will be blocked to prevent locking everyone out of user administration.',
        'service_category_id' => 'Service category',
        'service_category_id_placeholder' => 'Management (all categories)',
        'service_category_id_helper' => 'Scopes which tenders this user can see. Leave blank for management-level users who need to see every category.',
        'rights' => 'Individually assignable rights',
        'rights_helper' => 'Grants data access independent of role. A user keeps these rights even if their role changes.',
        'created_at' => 'Created at',
    ],
    'validation' => [
        'last_super_admin' => 'This is the only super admin left. Assign the super admin role to someone else first, or you\'ll lock everyone out of user administration.',
    ],
];
