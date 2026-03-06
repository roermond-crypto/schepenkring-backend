<?php

return [
    'min_increment' => (float) env('BID_MIN_INCREMENT', 500),
    'verification_ttl_minutes' => (int) env('BID_VERIFICATION_TTL_MINUTES', 1440),
    'session_ttl_days' => (int) env('BID_SESSION_TTL_DAYS', 365),
    'verify_url' => env('BID_VERIFY_URL', 'https://schepenkring.nl/bid-verify'),
    'rate_limit_per_minute' => (int) env('BID_RATE_LIMIT_PER_MINUTE', 5),
];
