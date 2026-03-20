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
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-5-mini'),
        'chat_timeout' => env('OPENAI_CHAT_TIMEOUT', 45),
        'chat_max_output_tokens' => env('OPENAI_CHAT_MAX_OUTPUT_TOKENS', 450),
        'mapping_model' => env('OPENAI_MAPPING_MODEL', 'gpt-4o-mini'),
        'mapping_timeout' => env('OPENAI_MAPPING_TIMEOUT', 120),
        'translation_model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
        'translation_timeout' => env('OPENAI_TRANSLATION_TIMEOUT', 30),
        'insights_model' => env('OPENAI_INSIGHTS_MODEL', 'gpt-5'),
        'insights_timeout' => env('OPENAI_INSIGHTS_TIMEOUT', 90),
        'insights_max_output_tokens' => env('OPENAI_INSIGHTS_MAX_OUTPUT_TOKENS', 2500),
        'insights_reasoning_effort' => env('OPENAI_INSIGHTS_REASONING_EFFORT', 'medium'),
    ],

    'pinecone' => [
        'key'  => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'chat_model' => env('GEMINI_CHAT_MODEL', 'gemini-2.5-flash'),
        'chat_timeout' => env('GEMINI_CHAT_TIMEOUT', 45),
        'chat_max_output_tokens' => env('GEMINI_CHAT_MAX_OUTPUT_TOKENS', 450),
    ],

    'chat_ai' => [
        'provider' => env('CHAT_AI_PROVIDER'),
    ],

    'pinecone' => [
        'key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
        'namespace' => env('PINECONE_NAMESPACE', 'copilot'),
    ],

    'signhost' => [
        'base_url' => env('SIGNHOST_BASE_URL', 'https://api.signhost.com/api/'),
        'app_key' => env('SIGNHOST_APP_KEY'),
        'user_token' => env('SIGNHOST_USER_TOKEN'),
        'shared_secret' => env('SIGNHOST_SHARED_SECRET'),
        'webhook_auth' => env('SIGNHOST_WEBHOOK_AUTH'),
    ],

    'telnyx' => [
        'base_url' => env('TELNYX_BASE_URL', 'https://api.telnyx.com/v2'),
        'api_key' => env('TELNYX_API_KEY'),
        'webhook_public_key' => env('TELNYX_WEBHOOK_PUBLIC_KEY'),
        'webhook_secret' => env('TELNYX_WEBHOOK_SECRET'),
        'connection_id' => env('TELNYX_CONNECTION_ID'),
        'application_id' => env('TELNYX_APPLICATION_ID'),
    ],

    'yext' => [
        'api_key' => env('YEXT_API_KEY'),
        'account_id' => env('YEXT_ACCOUNT_ID'),
        'entity_id' => env('YEXT_ENTITY_ID'),
        'api_base' => env('YEXT_API_BASE', 'https://api.yextapis.com'),
        'api_version' => env('YEXT_API_VERSION', '20240101'),
        'use_publisher_targets' => env('YEXT_USE_PUBLISHER_TARGETS', true),
        'use_video_upload' => env('YEXT_USE_VIDEO_UPLOAD', false),
        'video_publishers' => array_filter(array_map('trim', explode(',', env('YEXT_VIDEO_PUBLISHERS', 'facebook,instagram')))),
        'analytics_endpoint' => env('YEXT_ANALYTICS_ENDPOINT'),
    ],

];
