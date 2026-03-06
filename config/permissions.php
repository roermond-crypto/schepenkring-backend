<?php

return [
    'roles' => [
        'LOCATION_MANAGER' => [
            'tasks.view',
            'tasks.manage',
            'tasks.assign',
            'tasks.status',
            'tasks.delete',
            'tasks.comment',
            'tasks.attachment',
            'tasks.automation',
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
            'tasks.view',
            'tasks.status',
            'tasks.comment',
            'tasks.attachment',
            'clients.view',
            'clients.edit',
            'boats.view',
            'boats.edit',
            'leads.view',
            'leads.manage',
        ],
    ],
];
