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
        'card_types' => array_map('trim', explode(',', env('CARD_TYPES_LIST', ''))),
        'uris' => [
            'auth' => env('BASE_URI') . env('AUTH_URI'),
            'reports' => [
                'overall' => env('BASE_URI') . env('REPORT_URI') . env('OVERALL_SUFFIX'),
                'deposits' => env('BASE_URI') . env('REPORT_URI') . env('DEPOSITS_SUFFIX'),
                'depositDetails' => env('BASE_URI') . env('REPORT_URI') . env('DEPOSITS_DETAILS_SUFFIX'),
                'packages' => env('BASE_URI') . env('REPORT_URI') . env('PACKAGES_SUFFIX'),
                'accrual_packages' => env('BASE_URI') . env('REPORT_URI') . env('ACC_PACKAGES_SUFFIX'),
                'services' => env('BASE_URI') . env('REPORT_URI') . env('SERVICES_SUFFIX'),
                'accrual_services' => env('BASE_URI') . env('REPORT_URI') . env('ACC_SERVICES_SUFFIX'),
            ],
        ],
    ],

    'gingr' => [
        'api_key' => env('GINGR_API_KEY'),
        'username' => env('GINGR_USERNAME'),
        'password' => env('GINGR_PASSWORD'),
        'sandbox_username' => env('GINGR_SB_USERNAME'),
        'sandbox_password' => env('GINGR_SB_PASSWORD'),
        'queue_delay' => env('QUEUE_DELAY'),
        'mealmap_dpp' => env('MEALMAP_DOGS_PER_PAGE'),
        'yards_to_open' => env('YARDS_TO_OPEN'),
        'sandbox_service_condition' => env('SB_SERVICE_CONDITION', '='),
        'special_service_cats' => array_map('trim', explode(',', env('SPECIAL_SERVICE_CATEGORIES', ''))),
        'bath_service_cats' => array_map('trim', explode(',', env('BATH_SERVICE_CATEGORIES', ''))),
        'fsg_service_cats' => array_map('trim', explode(',', env('FSG_SERVICE_CATEGORIES', ''))),
        'location_id' => env('GINGR_LOCATION_ID', 3),
        'uris' => [
            'login' => env('GINGR_BASE_URL') . env('GINGR_LOGIN_URI'),
            'dashboard' => env('GINGR_BASE_URL') . '/dashboard',
            'icons' => env('GINGR_BASE_URL') . '/dashboard/get_icons',
            'checkedIn' => env('GINGR_BASE_URL') . env('GINGR_CHECKED_IN_URI'),
            'animalData' => env('GINGR_BASE_URL') . env('GINGR_ANIMAL_DATA_URI'),
            'ownerData' => env('GINGR_BASE_URL') . env('GINGR_OWNER_DATA_URI'),
            'servicesByType' => env('GINGR_BASE_URL') . env('GINGR_SERVICES_BY_TYPE_URI'),
            'charges_raw' => env('GINGR_BASE_URL') . '/reports/charges_raw',
            'payments_refunds_raw' => env('GINGR_BASE_URL') . '/reports/payments_refunds_raw',
            'lodging_occupancy' => env('GINGR_BASE_URL') . '/reports/lodging_occupancy',
            'sectionCounts' => env('GINGR_BASE_URL') . '/dashboard/section_counts',
        ],
    ],

    'google_calendar' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    ],

    'wiw' => [
        'email' => env('WIW_EMAIL'),
        'password' => env('WIW_PASSWORD'),
    ],
];
