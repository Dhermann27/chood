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
    ],

    'gingr' => [
        'api_key' => env('GINGR_API_KEY'),
        'widget_key' => env('GINGR_ACCOUNT_WIDGET_KEY'),
        'username' => env('GINGR_USERNAME'),
        'password' => env('GINGR_PASSWORD'),
        'queue_delay' => env('QUEUE_DELAY'),
        'location_id' => env('GINGR_LOCATION_ID', 3),
        'ids' => [
            'service_types' => array_map('intval', array_filter(array_map('trim', explode(',', env('GINGR_SERVICE_TYPE_IDS', ''))))),
            'boarding' => array_map('intval', array_filter(array_map('trim', explode(',', env('GINGR_BOARDING_TYPE_IDS', ''))))),
        ],
        'uris' => [
            'base' => env('GINGR_BASE_URL'),
            'login' => env('GINGR_BASE_URL') . env('GINGR_LOGIN_URI'),
            'dashboard' => env('GINGR_BASE_URL') . env('GINGR_DASHBOARD_URI'),
            'icons' => env('GINGR_BASE_URL') . env('GINGR_ICONS_URI'),
            'checkedIn' => env('GINGR_BASE_URL') . env('GINGR_CHECKED_IN_URI'),
            'ownerData' => env('GINGR_BASE_URL') . env('GINGR_OWNER_DATA_URI'),
            'servicesByType' => env('GINGR_BASE_URL') . env('GINGR_SERVICES_BY_TYPE_URI'),
            'sectionCounts' => env('GINGR_BASE_URL') . env('GINGR_SECTION_COUNTS_URI'),
            'reservationView' => env('GINGR_BASE_URL') . env('GINGR_RESERVATION_VIEW_URI'),
            'servicesByDate' => env('GINGR_BASE_URL') . env('GINGR_SERVICES_BY_DATE_URI'),
            'charges_raw' => env('GINGR_BASE_URL') . env('GINGR_CHARGES_RAW_URI'),
            'payments_refunds_raw' => env('GINGR_BASE_URL') . env('GINGR_PAYMENTS_REFUNDS_URI'),
            'lodging_occupancy' => env('GINGR_BASE_URL') . env('GINGR_LODGING_OCCUPANCY_URI'),
            'occupancy' => env('GINGR_BASE_URL') . env('GINGR_OCCUPANCY_URI'),
            'reservationsByDate' => env('GINGR_BASE_URL') . env('GINGR_RESERVATIONS_BY_DATE_URI'),
            'breeds' => env('GINGR_BASE_URL') . env('GINGR_BREEDS_URI'),
            'reservation_widget' => env('GINGR_BASE_URL') . '/api/v1/reservation_widget_data',
        ],
    ],

    'barkboard' => [
        'url' => env('BARKBOARD_URL'),
    ],

    'wiw' => [
        'email' => env('WIW_EMAIL'),
        'password' => env('WIW_PASSWORD'),
        'small_medium_only' => array_filter(array_map('trim', explode(',', env('SMALL_MEDIUM_ONLY_STAFF', '')))),
    ],
];
