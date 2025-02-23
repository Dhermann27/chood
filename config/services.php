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

    'dd' => [
        'uris' => [
            'base' => env('BASE_URI'),
            'auth' => env('BASE_URI') . env('AUTH_URI'),
            'inHouseList' => env('BASE_URI') . env('IN_HOUSE_LIST_URI'),
            'card' => env('BASE_URI') . env('CARD_URI'),
            'cardSuffix' => env('CARD_URI_SUFFIX'),
            'photo' => env('BASE_URI') . env('PHOTO_URI'),
            'reports' => [
                'overall' => env('BASE_URI') . env('REPORT_URI') . env('OVERALL_SUFFIX'),
                'deposits' => env('BASE_URI') . env('REPORT_URI') . env('DEPOSITS_SUFFIX'),
                'packages' => env('BASE_URI') . env('REPORT_URI') . env('PACKAGES_SUFFIX'),
                'services' => env('BASE_URI') . env('REPORT_URI') . env('SERVICES_SUFFIX'),
            ],

        ],
        'nodepath' => env('NODE_PATH'),
        'username' => env('DD_USERNAME'),
        'password' => env('DD_PASSWORD'),
    ],

    'homebase' => [
        'api_key' => env('HOMEBASE_API_KEY'),
        'loc_id' => env('HOMEBASE_LOCATION_ID'),
    ],

];
