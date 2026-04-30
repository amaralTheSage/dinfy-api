<?php

return [
    'currency' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
    'notification_url' => env('MERCADO_PAGO_NOTIFICATION_URL') ?: null,
    'plans' => [
        'monthly' => [
            'name' => 'Mensal',
            'reason' => env('MERCADO_PAGO_MONTHLY_REASON') ?: 'Dinfy  - Mensal',
            'amount' => env('MERCADO_PAGO_MONTHLY_PRICE'),
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 1,
            'frequency_type' => 'months',
            'monthly_equivalent' => env('MERCADO_PAGO_MONTHLY_PRICE'),
        ],
        'yearly' => [
            'name' => 'Anual',
            'reason' => env('MERCADO_PAGO_YEARLY_REASON') ?: 'Dinfy  - Anual',
            'amount' => env('MERCADO_PAGO_YEARLY_PRICE'),
            'currency_id' => env('MERCADO_PAGO_CURRENCY') ?: 'BRL',
            'frequency' => 12,
            'frequency_type' => 'months',
            'monthly_equivalent' => env('MERCADO_PAGO_YEARLY_PRICE') / 12,
        ],
    ],
];
