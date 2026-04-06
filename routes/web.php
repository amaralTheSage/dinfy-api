<?php

use App\Services\Subscriptions\SubscriptionCatalog;
use Illuminate\Support\Facades\Route;

Route::get('/check', function (SubscriptionCatalog $catalog) {
    return response()->json([
        'flow' => 'pix',
        'gateway' => 'mercado_pago',
        'plans' => $catalog->all(),
        'notes' => [
            'Create subscriptions through POST /api/subscriptions/checkout.',
            'New checkouts are PIX-first and return the local subscription plus the latest PIX invoice data.',
            'Legacy Mercado Pago preapproval ids may still appear on older records and webhooks.',
        ],
    ]);
});
