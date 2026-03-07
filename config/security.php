<?php

return [
    'levels' => [
        'low' => [
            'required_strength' => 'password',
            'fresh_minutes' => null,
        ],
        'medium' => [
            'required_strength' => 'password',
            'fresh_minutes' => null,
        ],
        'high' => [
            'required_strength' => 'otp',
            'fresh_minutes' => (int) env('SECURITY_HIGH_FRESH_MINUTES', 30),
        ],
    ],
    'webhooks' => [
        'max_age_seconds' => env('WEBHOOK_MAX_AGE_SECONDS', 300),
        'rate_limit_per_minute' => env('WEBHOOK_RATE_LIMIT_PER_MINUTE', 120),
        'processing_lock_seconds' => env('WEBHOOK_PROCESSING_LOCK_SECONDS', 300),
    ],
];
