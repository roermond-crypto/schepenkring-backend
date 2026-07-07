<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | These values configure the CORS headers sent with every API response.
    | The boat-intake endpoints (photo/document upload) are called directly
    | from the public schepen-kring.nl frontend — they need broad origin
    | support. Sanctum-authenticated routes rely on the same origin and
    | credentials, so supports_credentials stays false for public routes.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
