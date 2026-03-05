<?php

return [
    'roles' => [
        'LOCATION_MANAGER' => [
            'clients.view',
            'clients.edit',
            'clients.impersonate',
            'boats.view',
            'boats.edit',
            'boats.publish',
            'leads.view',
            'leads.manage',
            'staff.view',
            'staff.manage',
        ],
        'LOCATION_EMPLOYEE' => [
            'clients.view',
            'clients.edit',
            'boats.view',
            'boats.edit',
            'leads.view',
            'leads.manage',
        ],
    ],
];
