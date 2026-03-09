<?php

return [
    'base_url' => env('WHATSAPP_360_BASE_URL', 'https://waba-v2.360dialog.io'),
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
