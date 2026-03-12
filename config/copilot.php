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
    'knowledge' => [
        'top_k' => (int) env('COPILOT_KNOWLEDGE_TOP_K', 8),
        'max_sources' => (int) env('COPILOT_KNOWLEDGE_MAX_SOURCES', 3),
        'strong_match_score' => (float) env('COPILOT_KNOWLEDGE_STRONG_SCORE', 0.82),
        'merge_match_score' => (float) env('COPILOT_KNOWLEDGE_MERGE_SCORE', 0.68),
        'minimum_match_score' => (float) env('COPILOT_KNOWLEDGE_MIN_SCORE', 0.52),
        'strong_margin' => (float) env('COPILOT_KNOWLEDGE_STRONG_MARGIN', 0.05),
        'db_fallback_limit' => (int) env('COPILOT_KNOWLEDGE_DB_FALLBACK_LIMIT', 5),
    ],
    'learning' => [
        'enabled' => (bool) env('COPILOT_LEARNING_ENABLED', true),
        'min_occurrences' => (int) env('COPILOT_LEARNING_MIN_OCCURRENCES', 3),
        'lookback_days' => (int) env('COPILOT_LEARNING_LOOKBACK_DAYS', 30),
        'refresh_interval_seconds' => (int) env('COPILOT_LEARNING_REFRESH_INTERVAL', 300),
        'memory_top_k' => (int) env('COPILOT_MEMORY_TOP_K', 5),
        'auto_create_enabled' => (bool) env('COPILOT_AUTO_CREATE_ENABLED', true),
        'auto_create_threshold' => (float) env('COPILOT_AUTO_CREATE_THRESHOLD', 0.78),
        'search_result_min_score' => (float) env('COPILOT_LEARNING_SEARCH_RESULT_MIN_SCORE', 0.72),
    ],
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
