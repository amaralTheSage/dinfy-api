<?php

namespace App\Http\Controllers;

use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {
    }

    public function __invoke(Request $request)
    {
        Log::info('1. Entrou em MercadoPagoWebhookController@__invoke', [
            'type' => $request->input('type'),
            'topic' => $request->input('topic'),
            'data_id' => $request->query('data.id'),
        ]);

        $this->subscriptions->handleWebhook($request);

        Log::info('9. MercadoPagoWebhookController@__invoke finalizado com sucesso');

        return response()->json([
            'ok' => true,
        ]);
    }
}
