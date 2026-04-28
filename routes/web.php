<?php

use App\Services\Subscriptions\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/check', function (SubscriptionCatalog $catalog) {
    return response()->json([
        'flow' => 'pix',
        'gateway' => 'mercado_pago',
        'plans' => $catalog->all(),
        'notes' => [
            'Create subscriptions through POST /api/subscriptions/checkout.',
            'New checkouts are PIX-first and return the local subscription plus the latest PIX invoice data.',
            'Mercado Pago webhooks should use the payment topic to reconcile PIX charges.',
        ],
    ]);
});

Route::get('/test-of', function () {
    return view('api_test');
});

Route::post('/open-finance-test', function (Request $request) {
    Log::info($request);

    $headers = $request->validate(['tokensh' => 'required', 'cnpjsh' => 'required']);

    $body = $request->validate([
        'name' => 'required',
        'cpfCnpj' => 'required',
        'neighborhood' => 'required',
        'addressNumber' => 'required',
        'zipcode' => 'required',
        'state' => 'required',
        'city' => 'required',
        'statementActived' => 'required',
    ]);

    $api_url = env('OPENFINANCE_API_URL') . 'payer';

    $response = Http::withHeaders($headers)->post($api_url, $body);

    Log::info($response);

    return redirect()->back()->with(['response' => $response]);
})->name('openfinance.create_payer');
