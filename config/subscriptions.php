<?php

return [
    'provider' => 'mercado_pago',
    'currency' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
    'back_url' => env('MERCADO_PAGO_BACK_URL') ?: env('APP_URL', 'http://localhost'),
    'notification_url' => env('MERCADO_PAGO_NOTIFICATION_URL') ?: null,
    'plans' => [
        'monthly' => [
            'name' => 'Mensal',
            'reason' => env('MERCADO_PAGO_MONTHLY_REASON') ?: 'Dinfy Premium - Mensal',
            'amount' => 19.90,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 1,
            'frequency_type' => 'months',
            'monthly_equivalent' => 19.90,
            'checkout_mode' => 'subscription_pending',
        ],
        'yearly' => [
            'name' => 'Anual',
            'reason' => env('MERCADO_PAGO_YEARLY_REASON') ?: 'Dinfy Premium - Anual',
            'amount' => 97.00,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 12,
            'frequency_type' => 'months',
            'monthly_equivalent' => 8.08,
            'checkout_mode' => 'subscription_pending',
        ],
    ],
];
