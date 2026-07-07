<?php

return [
    'cmp' => [
        'base_url' => env('CMP_BASE_URL'),
        'api_key'  => env('CMP_API_KEY'),
    ],

    'fireflies' => [
        'api_key' => env('FIREFLIES_API_KEY'),
    ],

    'freeagent' => [
        'access_token' => env('FREEAGENT_ACCESS_TOKEN'),
        'refresh_token' => env('FREEAGENT_REFRESH_TOKEN'),
        'client_id' => env('FREEAGENT_CLIENT_ID'),
        'client_secret' => env('FREEAGENT_CLIENT_SECRET'),
    ],

    'onboarding_helpdesk' => [
        'base_url' => env('ONBOARDING_HELPDESK_URL'),
        'api_key' => env('ONBOARDING_HELPDESK_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'alerts' => [
        'account_manager_email' => env('ALERT_EMAIL'),
    ],
];
