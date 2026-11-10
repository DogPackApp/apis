<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'seller_auth_webhook' => env('SLACK_SELLER_AUTH_WEBHOOK'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'marketplace_stripe' => [
        'key' => env('MARKETPLACE_STRIPE_KEY'),
        'public_key' => env('MARKETPLACE_STRIPE_PUBLIC_KEY'),
        'wb_secret' => env('MARKETPLACE_STRIPE_WS_SECRET'),
        'wb_secret_subscription' => env('MARKETPLACE_STRIPE_WS_SECRET_SUBSCRIPTION'),
        'wb_stripe_payment_capture' => env('MARKETPLACE_STRIPE_WS_SECRET_PAYMENT_CAPTURE'),
    ],

    'marketplace_stripe_uk' => [
        'key' => env('MARKETPLACE_STRIPE_UK_KEY'),
        'public_key' => env('MARKETPLACE_STRIPE_UK_PUBLIC_KEY'),
        'wb_secret' => env('MARKETPLACE_STRIPE_UK_WS_SECRET'),
        'wb_secret_subscription' => env('MARKETPLACE_STRIPE_UK_WS_SECRET_SUBSCRIPTION'),
        'wb_stripe_payment_capture' => env('MARKETPLACE_STRIPE_UK_WS_SECRET_PAYMENT_CAPTURE'),
    ],

    'marketplace_fe_url' => env('MARKETPLACE_FE_URL'),
    'marketplace_media' => env('MARKETPLACE_CLOUDFRONT_URL'),
    'website_url' => env('WEBSITE_URL'),
    'website_assets_url' => env('WEBSITE_ASSETS_URL'),
    'support_phone' => env('SUPPORT_PHONE'),
    'DOGPACK_EMAIL' => env('DOGPACK_EMAIL'),
    'marketplace_google_api' => env('MARKETPLACE_GOOGLE_API'),
    'marketplace_easypost_api' => env('MARKETPLACE_EASYPOST_API'),

    'open_ai' => [
        'ai_product_description_key' => env('OPEN_AI_PRODUCT_DESCRIPTION_KEY'),
        'api_key' => env('OPEN_AI_API_KEY'),
    ],

];
