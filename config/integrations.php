<?php

return [
    'cmp' => [
        'base_url' => env('CMP_BASE_URL'),
        'api_key'  => env('CMP_API_KEY'),
    ],

    'freshdesk' => [
        'api_key' => env('FRESHDESK_API_KEY'),
        'domain'  => env('FRESHDESK_DOMAIN'),
    ],

    'fireflies' => [
        'api_key' => env('FIREFLIES_API_KEY'),
    ],

    'freeagent_tools' => [
        'base_url' => env('FREEAGENT_TOOLS_BASE_URL', 'https://freeagent-tools.despatchcloud.com'),
        'token'    => env('FREEAGENT_TOOLS_TOKEN'),
    ],

    'onboarding_helpdesk' => [
        'base_url' => env('ONBOARDING_HELPDESK_URL'),
        'api_key' => env('ONBOARDING_HELPDESK_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    // No scheduled contact falls due before this date (YYYY-MM-DD)
    'contact_schedule_starts_on' => env('CONTACT_SCHEDULE_STARTS_ON', '2026-11-03'),

    // Our own staff domains: never used to match a call or contact to a client
    'internal_email_domains' => array_filter(array_map('trim', explode(',', env('INTERNAL_EMAIL_DOMAINS', 'thedespatchcompany.com')))),

    'alerts' => [
        'account_manager_email' => env('ALERT_EMAIL'),
    ],
];
