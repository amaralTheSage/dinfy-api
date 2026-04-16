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

    'workos' => [
        'client_id' => env('WORKOS_CLIENT_ID'),
        'api_key' => env('WORKOS_API_KEY'),
    ],

    'oauth' => [
        'allowed_redirect_uris' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('OAUTH_ALLOWED_REDIRECT_URIS', 'dinfy://auth-callback'))
        ))),
        'fallback_redirect_uri' => env('OAUTH_FALLBACK_REDIRECT_URI', 'dinfy://auth-callback'),
        'state_ttl_minutes' => (int) env('OAUTH_STATE_TTL_MINUTES', 10),
        'exchange_code_ttl_minutes' => (int) env('OAUTH_EXCHANGE_CODE_TTL_MINUTES', 5),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
    ],

    'n8n' => [
        'whatsapp_opt_in_url' => env('N8N_WHATSAPP_OPT_IN_URL', 'https://n8n.dinfy.app/webhook/opt-in'),
    ],

];
