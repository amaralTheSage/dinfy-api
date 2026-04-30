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

Route::post('/create-payer-test', function (Request $request) {
    Log::info($request);

    $headers = ['tokensh' => config('openfinance.tokensh'), 'cnpjsh' => config('openfinance.cnpjsh')];

    $body = $request->validate([
        'name' => 'required',
        'cpfCnpj' => 'required',
        'neighborhood' => 'required',
        'addressNumber' => 'required',
        'zipcode' => 'required',
        'state' => 'required',
        'city' => 'required',
    ]);

    $body = array_merge($body, ['statementActivated' => true]);

    $api_url = config('openfinance.url') . 'payer';

    $response = Http::withHeaders($headers)->post($api_url, $body);

    $data = $response->json();

    if ($data['errors']['internalCode'] ?? null) {
        Log::debug($data['errors']['internalCode']);
    }

    $headersWithPayer = $body['cpfCnpj'] ? array_merge($headers, ['payercpfcnpj' => $body['cpfCnpj']]) : throw new Exception('ERROR WHEN CREATING PAYER: CPF/CNPJ is required for the next step. Payer probably was not created successfully.');

    if (($data['errors']['internalCode'] ?? null) == 7632) {
        Log::debug('Hit internal code 7632');

        $response = Http::withHeaders($headersWithPayer)->put($api_url, ['statementActivated' => true]);
    }

    Log::info('Response', $response->json());

    return redirect()->back()->with(['response' => $response->json()]);
})->name('openfinance.create_payer');

Route::post('/create-account-test', function (Request $request) {

    Log::debug('CREATE ACCOUNT **REQUEST: ', $request->all());

    $body = $request->validate([
        'cpfCnpj' => 'required|string',
        'bankCode' => 'string|required|max:3',
        'agency' => 'string|required|max:4',
        'agencyDigit' => 'string|nullable|max:2',
        'accountNumber' => 'string|required|max:12',
    ]);

    $body = array_merge($body, ['statementActivated' => true]);

    $headers = ['tokensh' => config('openfinance.tokensh'), 'cnpjsh' => config('openfinance.cnpjsh')];

    $headers = $body['cpfCnpj'] ? array_merge($headers, ['payercpfcnpj' => $body['cpfCnpj']]) : throw new Exception('error when creating **ACCOUNT**: CPF/CNPJ is required for the next step. Payer probably was not created successfully.');

    $api_url = config('openfinance.url') . 'account';

    $response = Http::withHeaders($headers)->post($api_url, $body);

    Log::debug('CREATE ACCOUNT **RESPONSE STATUS: ', ['status' => $response->status()]);
    Log::debug('CREATE ACCOUNT **RESPONSE BODY: ', ['body' => $response->body()]);

    $responseData = $response->json() ?? $response->body();

    Log::debug('CREATE ACCOUNT **RESPONSE: ', is_array($responseData) ? $responseData : ['raw' => $responseData]);

    return redirect()->back()->with(['response_account' => $responseData]);

    //  # passo 2: Criar conta
    // Método:

    // POST https://staging.pagamentobancario.com.br/api/v1/account
    // Requisição:

    // [
    //   {
    //     "bankCode": "001", // banco do brasil
    //     "agency": "1111",
    //     "accountNumber": "0001",
    //     "statementActived": true
    //   }
    // ]

    // RESPOSTA:

    // {
    //   "accounts": [
    //     {
    //       "bankCode": "string",
    //       "accountHash": "string",
    //       "agency": "string",
    //       "agencyDigit": "string",
    //       "accountNumber": "string",
    //       "accountNumberDigit": "string",
    //       "convenioAgency": "string",
    //       "convenioNumber": "string",
    //       "remessaSequential": 0,
    //       "accountPayment": true,
    //       "statementActived": true,
    //       "openfinanceLink": "https://staging.pagamentobancario.com.br/gui/7e23a377f1264936a19c4689c7bffa0c"
    //     }
    //   ]
    // }
})->name('openfinance.create_account');
