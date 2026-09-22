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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'clients_api' => [
        'token' => env('CLIENTS_API_TOKEN', ''), // fallback vacío
    ],

    'justicia_alternativa' => [
        'url' => env('JUSTICIA_ALTERNATIVA_WEBAPP_URL', ''),
        'token' => env('JUSTICIA_ALTERNATIVA_TOKEN', ''),
        'timeout' => env('JUSTICIA_ALTERNATIVA_TIMEOUT', 20),
    ],

    'google_contracts' => [
        'templates' => [
            'lease_without_guarantor' => env('GOOGLE_CONTRACT_TEMPLATE_WITHOUT_GUARANTOR_ID'),
            'lease_with_guarantor' => env('GOOGLE_CONTRACT_TEMPLATE_WITH_GUARANTOR_ID'),
        ],
        'destination_folder_id' => env('GOOGLE_CONTRACT_DESTINATION_FOLDER_ID'),
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON'),
        'service_account_json_path' => env('GOOGLE_SERVICE_ACCOUNT_JSON_PATH'),
        'timeout' => (int) env('GOOGLE_CONTRACT_TIMEOUT', 20),
    ],
];
