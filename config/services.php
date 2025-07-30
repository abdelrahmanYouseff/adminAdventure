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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'noon' => [
        'api_key' => env('NOON_API_KEY'),
        'api_url' => env('NOON_API_URL', 'https://api-test.sa.noonpayments.com/payment/v1/'),
        'business_id' => env('NOON_BUSINESS_ID'),
        'app_id' => env('NOON_APP_ID'),
        'auth_header' => env('NOON_AUTH_HEADER'),
        'webhook_secret' => env('NOON_WEBHOOK_SECRET'),
    ],

];
