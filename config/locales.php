<?php

return [
    'supported' => ['nl', 'en', 'de', 'fr'],
    'default' => 'nl',
    'fallbacks' => [
        'de' => ['en', 'nl'],
        'en' => ['nl'],
        'nl' => ['en'],
        'fr' => ['en', 'nl'],
    ],
];
