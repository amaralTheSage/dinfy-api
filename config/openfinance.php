<?php

return [
    'url' => env('OPENFINANCE_API_URL'),
    'tokensh' => env('TOKENSH'),
    'cnpjsh' => env('CNPJSH'),
    'statement_sync' => [
        'rate_limit_minutes' => (int) env('OPENFINANCE_STATEMENT_RATE_LIMIT_MINUTES', 60),
        'pending_check_interval_minutes' => (int) env('OPENFINANCE_STATEMENT_PENDING_CHECK_INTERVAL_MINUTES', 15),
        'lookback_days' => (int) env('OPENFINANCE_STATEMENT_LOOKBACK_DAYS', 7),
        'batch_size' => (int) env('OPENFINANCE_STATEMENT_SYNC_BATCH_SIZE', 10),
        'log_statement_payloads' => (bool) env('OPENFINANCE_LOG_STATEMENT_PAYLOADS', true),
    ],
];
