<?php

namespace App\Http\Controllers;

use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function __invoke(Request $request)
    {
        Log::info('MercadoPagoWebhookController@__invoke was hit.');

        $this->subscriptions->handleWebhook($request);

        Log::info('MercadoPagoWebhookController@__invoke completed.');

        return response()->json([
            'ok' => true,
        ]);
    }
}
