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
        'payment_action' => env('NOON_PAYMENT_ACTION', 'Sale'),
        'cancel_url_enabled' => env('NOON_CANCEL_URL_ENABLED', false),
        'locale' => env('NOON_LOCALE', 'ar'),
        'order_category' => env('NOON_ORDER_CATEGORY', 'pay'),
        'order_channel' => env('NOON_ORDER_CHANNEL', 'WEB'),
        'store_checkout_debug' => env('STORE_CHECKOUT_DEBUG', false),
        'payment_return_url' => env('PAYMENT_RETURN_URL'),
    ],

    'store' => [
        /** When true, checkout skips Noon and auto-completes payment (for flow testing). */
        'mock_payment' => env('STORE_MOCK_PAYMENT', false),
    ],

    'authentica' => [
        'base_url' => env('AUTHENTICA_BASE_URL', 'https://api.authentica.sa/api/v2'),
        'api_key' => env('AUTHENTICA_API_KEY'),
        'template_id' => env('AUTHENTICA_TEMPLATE_ID', 31),
        'otp_length' => (int) env('AUTHENTICA_OTP_LENGTH', 4),
        'timeout' => (int) env('AUTHENTICA_TIMEOUT', 30),
        'app_name' => env('AUTHENTICA_APP_NAME', 'adventurworldapp'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'to' => env('WHATSAPP_TO', '966538388299'),
        'extra_to' => env('WHATSAPP_EXTRA_TO', '966538778559'),
        'dispatch_sync' => env('WHATSAPP_DISPATCH_SYNC', true),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    ],

];
