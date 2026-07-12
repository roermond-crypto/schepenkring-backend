<?php

// Global fallback contact details for outgoing emails when no location
// context exists yet (e.g. account registration, before a user has any
// client_location_id) — see EmailTemplateResolver::defaultTags(). Real
// values default to Schepenkring's actual HQ details (already used
// elsewhere in the app — locales/*.json's Support.* keys, the CMS
// NavigationSeeder) rather than placeholder text, so a misconfigured
// .env still sends something correct.
return [
    'name' => env('COMPANY_NAME', 'Schepenkring'),
    'phone' => env('COMPANY_PHONE', '+31 (0)320 711340'),
    'email' => env('COMPANY_EMAIL', 'lelystad@schepenkring.nl'),
    'address' => env('COMPANY_ADDRESS', 'Parkhaven 3, 8242 PE Lelystad'),
    'website' => env('COMPANY_WEBSITE', 'https://www.schepenkring.nl'),
    'logo_url' => env('COMPANY_LOGO_URL', ''),
    'kvk' => env('COMPANY_KVK', ''),
    'btw' => env('COMPANY_BTW', ''),
];
