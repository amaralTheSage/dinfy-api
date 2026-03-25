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
            'amount' => 0.5,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 1,
            'frequency_type' => 'months',
            'monthly_equivalent' => 0.01,
            'checkout_mode' => 'subscription_authorized',
            'preapproval_plan_id' => env('MERCADO_PAGO_MONTHLY_PLAN_ID') ?: null,
        ],
        'yearly' => [
            'name' => 'Anual',
            'reason' => env('MERCADO_PAGO_YEARLY_REASON') ?: 'Dinfy Premium - Anual',
            'amount' => 0.5,
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 12,
            'frequency_type' => 'months',
            'monthly_equivalent' => 0.01,
            'checkout_mode' => 'subscription_authorized',
            'preapproval_plan_id' => env('MERCADO_PAGO_YEARLY_PLAN_ID') ?: null,
        ],
    ],
];
