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

    'panther' => [
        'uris' => [
            'base' => env('BASE_URI'),
            'inHouseList' => env('BASE_URI') . env('IN_HOUSE_LIST_URI'),
            'card' => env('BASE_URI') . env('CARD_URI'),
            'cardSuffix' => env('CARD_URI_SUFFIX'),
            'photo' => env('BASE_URI') . env('PHOTO_URI'),
        ],
            'port' => env('PANTHER_PORT'),
            'username' => env('DD_USERNAME'),
            'password' => env('DD_PASSWORD'),
    ]

];