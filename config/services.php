<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'ga4' => [
        'property_id' => env('GA4_PROPERTY_ID'),
        'client_email' => env('GA4_CLIENT_EMAIL'),
        'private_key' => env('GA4_PRIVATE_KEY'),
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
        'cache_ttl' => env('GA4_DATA_CACHE_TTL', 3600),
        'dimension_harbor_id' => env('GA4_DIMENSION_HARBOR_ID', 'customEvent:harbor_id'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // ── NauticSecure Image Pipeline ──

    'cloudinary' => [
        'cloud_name'       => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'          => env('CLOUDINARY_API_KEY'),
        'api_secret'       => env('CLOUDINARY_API_SECRET'),
        'enhance_enabled'  => env('CLOUDINARY_ENHANCE_ENABLED', false),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],
    'signhost' => [
        'base_url' => env('SIGNHOST_BASE_URL', 'https://api.signhost.com/api/'),
        'app_key' => env('SIGNHOST_APP_KEY'),
        'user_token' => env('SIGNHOST_USER_TOKEN'),
        'shared_secret' => env('SIGNHOST_SHARED_SECRET'),
        'webhook_auth' => env('SIGNHOST_WEBHOOK_AUTH'),
    ],

];
