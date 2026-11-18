<?php

return [
    'api_key' => env('ELASTICSEARCH_API_KEY'),
    'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),

    'products' => [
        'alias' => env('ELASTICSEARCH_PRODUCTS_NAMESPACE', 'products'),
        'index_prefix' => env('ELASTICSEARCH_PRODUCTS_NAMESPACE', 'products'),
        'bulk_size' => (int) env('ELASTICSEARCH_PRODUCTS_BULK_SIZE', 50),
        'default_size' => (int) env('ELASTICSEARCH_PRODUCTS_DEFAULT_SIZE', 36),
        'max_size' => (int) env('ELASTICSEARCH_PRODUCTS_MAX_SIZE', 100),
    ],
];
