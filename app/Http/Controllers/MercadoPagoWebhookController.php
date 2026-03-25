<?php

namespace App\Http\Controllers;

use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {
    }

    public function __invoke(Request $request)
    {
        $this->subscriptions->handleWebhook($request);

        return response()->json([
            'ok' => true,
        ]);
    }
}
