<?php

return [
    'base_url' => env('WHATSAPP_360_BASE_URL', 'https://waba-v2.360dialog.io'),
    'sandbox_base_url' => env('WHATSAPP_360_SANDBOX_BASE_URL', 'https://waba-sandbox.360dialog.io'),
    'sandbox_number' => env('WHATSAPP_360_SANDBOX_NUMBER', '551146733492'),
    'sandbox_api_key' => env('WHATSAPP_360_SANDBOX_API_KEY'),
    'sandbox_webhook_url' => env('WHATSAPP_360_SANDBOX_WEBHOOK_URL'),
    'messages_path' => env('WHATSAPP_360_MESSAGES_PATH', '/v1/messages'),
    'webhook_config_path' => env('WHATSAPP_360_WEBHOOK_PATH', '/v1/configs/webhook'),
    'outbound_rate_limit_per_minute' => (int) env('WHATSAPP_OUTBOUND_RATE_LIMIT', 60),
    'opt_out_phrases' => [
        'stop',
        'unsubscribe',
        "don't message",
        'do not message',
        'opt out',
        'no whatsapp',
        'block',
    ],
];
