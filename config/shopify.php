<?php
return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', env('SHOPIFY_API_SECRET')),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products'),
    'app_url' => env('SHOPIFY_APP_URL', env('APP_URL')),
    'session_token_max_age' => 90,
    'retention_days' => (int) env('PROMOTION_RETENTION_DAYS', 90),
    'store_listing_url' => env('SHOPIFY_APP_STORE_URL'),
    'support_email' => env('SHOPIFY_SUPPORT_EMAIL'),
];
