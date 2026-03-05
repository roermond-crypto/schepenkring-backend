<?php

return [
    'platform_user_id' => env('WALLET_PLATFORM_USER_ID'),
    'default_currency' => env('WALLET_CURRENCY', 'EUR'),
    'payout_minimum' => (int) env('WALLET_PAYOUT_MINIMUM', 500),
];
