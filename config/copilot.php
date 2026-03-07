<?php

return [
    'rate_limit' => [
        'max_attempts' => (int) env('COPILOT_RATE_LIMIT_MAX', 30),
        'decay_seconds' => (int) env('COPILOT_RATE_LIMIT_DECAY', 60),
    ],
    'fuzzy_limit' => (int) env('COPILOT_FUZZY_LIMIT', 8),
    'ai_enabled' => (bool) env('COPILOT_AI_ENABLED', false),
    'ai_model' => env('COPILOT_AI_MODEL', 'gpt-4o-mini'),
    'ai_provider' => env('COPILOT_AI_PROVIDER', 'openai'),
    'answer_ai_enabled' => (bool) env('COPILOT_ANSWER_AI_ENABLED', false),
    'default_action_map' => [
        'invoice' => 'invoice.view',
        'boat' => 'boat.view',
        'harbor' => 'harbor.view',
        'user' => 'user.view',
        'payment' => 'payment.view',
        'deal' => 'deal.view',
    ],
    'search_routes' => [
        'invoice' => '/admin/invoices/{id}',
        'boat' => '/admin/yachts/{id}',
        'harbor' => '/admin/harbors/{id}',
        'user' => '/admin/users/{id}',
        'payment' => '/admin/payments/{id}',
        'deal' => '/admin/deals/{id}',
    ],
    'faq_action_map' => [
        'credit note' => 'invoice.credit_note',
        'invoice' => 'invoice.list',
        'boat' => 'boat.list',
    ],
];
