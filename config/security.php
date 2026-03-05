<?php

return [
    'login' => [
        'window_minutes' => (int) env('SECURITY_LOGIN_WINDOW_MINUTES', 30),
        'soft_lock_after' => (int) env('SECURITY_LOGIN_SOFT_LOCK_AFTER', 10),
        'soft_lock_minutes' => (int) env('SECURITY_LOGIN_SOFT_LOCK_MINUTES', 15),
        'delay_schedule' => [
            4 => 2,
            5 => 4,
            6 => 8,
        ],
        'captcha_after' => (int) env('SECURITY_LOGIN_CAPTCHA_AFTER', 7),
        'captcha_until' => (int) env('SECURITY_LOGIN_CAPTCHA_UNTIL', 9),
        'step_up_on_new_device' => env('SECURITY_LOGIN_STEP_UP_NEW_DEVICE', false),
        'step_up_on_new_country' => env('SECURITY_LOGIN_STEP_UP_NEW_COUNTRY', false),
        'impossible_travel_minutes' => (int) env('SECURITY_LOGIN_IMPOSSIBLE_TRAVEL_MINUTES', 120),
        'suspicious' => [
            'ip_unique_users' => (int) env('SECURITY_LOGIN_SUSPICIOUS_IP_USERS', 8),
            'user_unique_ips' => (int) env('SECURITY_LOGIN_SUSPICIOUS_USER_IPS', 5),
        ],
    ],
    'otp' => [
        'ttl_minutes' => (int) env('SECURITY_OTP_TTL_MINUTES', 5),
        'max_send_per_window' => (int) env('SECURITY_OTP_MAX_SEND', 3),
        'send_window_minutes' => (int) env('SECURITY_OTP_SEND_WINDOW', 15),
        'max_verify_attempts' => (int) env('SECURITY_OTP_MAX_VERIFY_ATTEMPTS', 10),
    ],
    'email_verification' => [
        'ttl_minutes' => (int) env('SECURITY_EMAIL_VERIFICATION_TTL_MINUTES', 15),
        'max_send_per_window' => (int) env('SECURITY_EMAIL_VERIFICATION_MAX_SEND', 5),
        'send_window_minutes' => (int) env('SECURITY_EMAIL_VERIFICATION_SEND_WINDOW', 10),
        'max_verify_per_window' => (int) env('SECURITY_EMAIL_VERIFICATION_MAX_VERIFY', 5),
        'verify_window_minutes' => (int) env('SECURITY_EMAIL_VERIFICATION_VERIFY_WINDOW', 10),
        'max_attempts' => (int) env('SECURITY_EMAIL_VERIFICATION_MAX_ATTEMPTS', 5),
        'lock_minutes' => (int) env('SECURITY_EMAIL_VERIFICATION_LOCK_MINUTES', 15),
    ],
    'captcha' => [
        'enabled' => env('SECURITY_CAPTCHA_ENABLED', false),
        'provider' => env('SECURITY_CAPTCHA_PROVIDER', 'hcaptcha'), // hcaptcha or recaptcha
        'secret' => env('SECURITY_CAPTCHA_SECRET'),
        'site_key' => env('SECURITY_CAPTCHA_SITE_KEY'),
    ],
    'tokens' => [
        'access_ttl_minutes' => env('SECURITY_ACCESS_TOKEN_TTL_MINUTES'),
    ],
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
        'payout' => [
            'required_strength' => 'otp',
            'fresh_minutes' => (int) env('SECURITY_PAYOUT_FRESH_MINUTES', 15),
        ],
    ],
    'audit' => [
        'allow_delete' => env('SECURITY_AUDIT_ALLOW_DELETE', false),
        'allow_update' => env('SECURITY_AUDIT_ALLOW_UPDATE', false),
    ],
    'headers' => [
        'frame_ancestors' => env('SECURITY_FRAME_ANCESTORS', "'none'"),
    ],
    'webhooks' => [
        'rate_limit_per_minute' => (int) env('SECURITY_WEBHOOK_RATE_LIMIT', 120),
        'max_age_seconds' => (int) env('SECURITY_WEBHOOK_MAX_AGE_SECONDS', 300),
    ],
];
