<?php

return [
    'token' => env('SHOPIFY_DRAGON_TOKEN'),
    'url' => env('SHOPIFY_DRAGON_URL'),

    'product_sync_max_execution_seconds' => (int) env('SHOPIFY_PRODUCT_SYNC_MAX_EXECUTION', 120),
];
