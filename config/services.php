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

    /*
     * حساب‌پی (HesabPay) — مستندات رسمی: docs.hesab.com (نسخه‌ی چاپی روی لپتاپ: resources/hesabpay/).
     * کلید فقط در `.env` سرور. بی کلید، درگاه «آماده نیست» و به مشتری نشان داده نمی‌شود.
     * HESABPAY_ENV: sandbox (آزمایشی، پول واقعی نیست) یا production.
     */
    'hesabpay' => [
        'env' => env('HESABPAY_ENV', 'sandbox'),
        'api_key' => env('HESABPAY_API_KEY'),
        // اگر خالی بود از روی env: sandbox → api-sandbox.hesab.com، production → api.hesab.com
        'api_url' => env('HESABPAY_API_URL'),
        'timeout' => (int) env('HESABPAY_TIMEOUT', 20),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
