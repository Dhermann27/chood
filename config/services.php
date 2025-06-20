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
            'allergy' => env('BASE_URI') . env('ALLERGY_URI'),
            'booking' => env('BASE_URI') . env('BOOKING_URI'),
            'card' => env('BASE_URI') . env('CARD_URI'),
            'feeding' => env('BASE_URI') . env('FEEDING_URI'),
            'med' => env('BASE_URI') . env('MED_URI'),
            'photo' => env('BASE_URI') . env('PHOTO_URI'),
            'reports' => [
                'overall' => env('BASE_URI') . env('REPORT_URI') . env('OVERALL_SUFFIX'),
                'accrual_packages' => env('BASE_URI') . env('REPORT_URI') . env('ACC_PACKAGES_SUFFIX'),
                'accrual_services' => env('BASE_URI') . env('REPORT_URI') . env('ACC_SERVICES_SUFFIX'),
                'deposits' => env('BASE_URI') . env('REPORT_URI') . env('DEPOSITS_SUFFIX'),
                'depositDetails' => env('BASE_URI') . env('REPORT_URI') . env('DEPOSITS_DETAILS_SUFFIX'),
                'packages' => env('BASE_URI') . env('REPORT_URI') . env('PACKAGES_SUFFIX'),
                'services' => env('BASE_URI') . env('REPORT_URI') . env('SERVICES_SUFFIX'),
            ],
            'servicelist' => env('BASE_URI') . env('SERVICELIST_URI'),

        ],
        'mealmap_dpp' => env('MEALMAP_DOGS_PER_PAGE'),
        'nodepath' => env('NODE_PATH'),
        'queue_delay' => env('QUEUE_DELAY'),
        'regular_service_cats' => array_map('trim', explode(',', env('REGULAR_SERVICE_CATEGORIES'))),
        'special_service_cats' => array_map('trim', explode(',', env('SPECIAL_SERVICE_CATEGORIES'))),
        'bath_service_cats' => array_map('trim', explode(',', env('BATH_SERVICE_CATEGORIES'))),
        'fsg_service_cats' => array_map('trim', explode(',', env('FSG_SERVICE_CATEGORIES'))),
        'sandbox_service_condition' => env('SB_SERVICE_CONDITION', '='),
        'yards_to_open' => env('YARDS_TO_OPEN'),
        'username' => env('DD_USERNAME'),
        'password' => env('DD_PASSWORD'),
        'sandbox_username' => env('SB_USERNAME'),
        'sandbox_password' => env('SB_PASSWORD'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'engine' => env('OPENAI_ENGINE'),
    ],

    'homebase' => [
        'api_key' => env('HOMEBASE_API_KEY'),
        'loc_id' => env('HOMEBASE_LOCATION_ID'),
    ],
];
