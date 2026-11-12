<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    ],

    'tally' => [
        'driver' => env('TALLY_DRIVER', 'simulator'),
        'host' => env('TALLY_HOST', '127.0.0.1'),
        'port' => (int) env('TALLY_PORT', 9000),
        'url' => env('TALLY_URL', 'http://127.0.0.1:9000'),
        'company' => env('TALLY_COMPANY_NAME', 'Malabar Club'),
        'include_cost_centres' => (bool) env('TALLY_INCLUDE_COST_CENTRES', false),
        'auto_sync' => (bool) env('TALLY_AUTO_SYNC', false),
    ],

];
