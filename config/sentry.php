<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('SENTRY_RELEASE', env('APP_VERSION')),

    // Attach user context (PII) only if explicitly allowed.
    'send_default_pii' => env('SENTRY_SEND_PII', true),

    // Performance monitoring
    // Env values are strings, but the SDK expects float|int|null.
    'traces_sample_rate' => ($traceRate = env('SENTRY_TRACES_SAMPLE_RATE', 0.0)) === null ? null : (float) $traceRate,
    'profiles_sample_rate' => ($profileRate = env('SENTRY_PROFILES_SAMPLE_RATE', 0.0)) === null ? null : (float) $profileRate,

    // Breadcrumbs
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'queue' => true,
        'http' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
    ],
];
