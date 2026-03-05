<?php

return [
    'partner_agreement' => [
        'version' => env('PARTNER_AGREEMENT_VERSION', 'v1'),
        'path' => env('PARTNER_AGREEMENT_PATH', resource_path('legal/partner_agreement_v1.txt')),
    ],
];
