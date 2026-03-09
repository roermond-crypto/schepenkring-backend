<?php

return [
    'provider' => env('VOICE_PROVIDER', 'telnyx'),
    'gateway_url' => env('VOICE_GATEWAY_URL'),
    'internal_secret' => env('VOICE_INTERNAL_SECRET'),
    'conversation_reuse_days' => (int) env('VOICE_CONVERSATION_REUSE_DAYS', 90),
    'min_balance_eur' => (float) env('VOICE_MIN_BALANCE_EUR', 1.00),
    'cost_per_minute_eur' => (float) env('VOICE_COST_PER_MINUTE_EUR', 0.05),
    'free_seconds_threshold' => (int) env('VOICE_FREE_SECONDS_THRESHOLD', 10),
    'call_limit_per_hour' => (int) env('VOICE_CALL_LIMIT_PER_HOUR', 5),
    'call_limit_per_day' => (int) env('VOICE_CALL_LIMIT_PER_DAY', 20),
    'block_duration_minutes' => (int) env('VOICE_BLOCK_DURATION_MINUTES', 1440),
    'default_country_dial_code' => env('VOICE_DEFAULT_COUNTRY_DIAL_CODE'),
    'recordings' => [
        'download' => env('VOICE_RECORDINGS_DOWNLOAD', true),
        'disk' => env('VOICE_RECORDINGS_DISK', 'public'),
        'path' => env('VOICE_RECORDINGS_PATH', 'call-recordings'),
    ],
];
