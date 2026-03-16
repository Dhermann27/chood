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

    'gingr' => [
        'api_key' => env('GINGR_API_KEY'),
        'username' => env('GINGR_USERNAME'),
        'password' => env('GINGR_PASSWORD'),
        'queue_delay' => env('QUEUE_DELAY'),
        'mealmap_dpp' => env('MEALMAP_DOGS_PER_PAGE'),
        'yards_to_open' => env('YARDS_TO_OPEN'),
        'sandbox_service_condition' => env('SB_SERVICE_CONDITION', '='),
        'special_service_cats' => array_map('trim', explode(',', env('SPECIAL_SERVICE_CATEGORIES', ''))),
        'bath_service_cats' => array_map('trim', explode(',', env('BATH_SERVICE_CATEGORIES', ''))),
        'fsg_service_cats' => array_map('trim', explode(',', env('FSG_SERVICE_CATEGORIES', ''))),
        'uris' => [
            'login' => env('GINGR_BASE_URL') . '/auth/login',
            'checkedIn' => env('GINGR_BASE_URL') . '/api/v1/get_checked_in_reservations',
            'animalData' => env('GINGR_BASE_URL') . '/api/v1/get_animal_data',
            'ownerData' => env('GINGR_BASE_URL') . '/owners/get_form_data/id/',
        ],
    ],

    'google_calendar' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    ],

    'homebase' => [
        'api_key' => env('HOMEBASE_API_KEY'),
        'loc_id' => env('HOMEBASE_LOCATION_ID'),
    ],
];
