<?php

return [
    'currency' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
    'back_url' => env('MERCADO_PAGO_BACK_URL') ?: env('APP_URL', 'http://localhost'),
    'notification_url' => env('MERCADO_PAGO_NOTIFICATION_URL') ?: null,
    'payment_session_ttl_minutes' => (int) (env('MERCADO_PAGO_PAYMENT_SESSION_TTL_MINUTES') ?: 30),
    'app_return_url' => env('DINFY_APP_SUBSCRIPTION_RETURN_URL') ?: 'dinfy://subscription',
    'plans' => [
        'monthly' => [
            'name' => 'Mensal',
            'reason' => env('MERCADO_PAGO_MONTHLY_REASON') ?: 'Dinfy  - Mensal',
            'amount' => 19.90,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 1,
            'frequency_type' => 'months',
            'monthly_equivalent' => 19.90,
        ],
        'yearly' => [
            'name' => 'Anual',
            'reason' => env('MERCADO_PAGO_YEARLY_REASON') ?: 'Dinfy  - Anual',
            'amount' => 97.00,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 12,
            'frequency_type' => 'months',
            'monthly_equivalent' => 8.08,
        ],
    ],
];
