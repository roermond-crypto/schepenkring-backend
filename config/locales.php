<?php

return [
    'supported' => ['nl', 'en', 'de'],
    'default' => 'nl',
    'fallbacks' => [
        'de' => ['en', 'nl'],
        'en' => ['nl'],
        'nl' => ['en'],
    ],
];
