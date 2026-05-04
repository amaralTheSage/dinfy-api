<?php

use App\Services\Subscriptions\SubscriptionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

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

// Tela única de teste manual do fluxo Open Finance/Tecnospeed.
// Não é parte da integração real do produto; serve para exercitar os passos abaixo.
Route::get('/test-of', function () {
    return view('api_test');
});

// Passo 1: criar ou reativar o payer na Tecnospeed.
// A integração real deve mover essa lógica para um service/controller próprio.
// Headers de SH vêm de config/env; o CPF/CNPJ criado aqui vira payercpfcnpj nos próximos passos.
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

// Passo 2: criar a conta bancária do payer.
// A resposta da Tecnospeed retorna accountHash e openfinanceLink.
// O usuário deve abrir o openfinanceLink e autorizar a conexão Open Finance; a ativação pode levar horas.
// O accountHash retornado é obrigatório para pedir o extrato no passo 3.
Route::post('/create-account-test', function (Request $request) {

    Log::debug('CREATE ACCOUNT **REQUEST: ', $request->all());

    $validator = Validator::make($request->all(), [
        'cpfCnpj' => 'required|string',
        'bankCode' => 'string|required|max:3',
        'agency' => 'string|required|max:4',
        'agencyDigit' => 'string|nullable|max:2',
        'accountNumber' => 'string|required|max:12',
        'accountNumberDigit' => 'string|nullable|max:2',
    ]);

    if ($validator->fails()) {
        Log::debug('CREATE ACCOUNT **VALIDATION ERRORS: ', $validator->errors()->toArray());

        return redirect()->back()->withErrors($validator)->withInput();
    }

    $body = $validator->validated();

    $headers = ['tokensh' => config('openfinance.tokensh'), 'cnpjsh' => config('openfinance.cnpjsh')];

    $headers = $body['cpfCnpj'] ? array_merge($headers, ['payercpfcnpj' => $body['cpfCnpj']]) : throw new Exception('error when creating **ACCOUNT**: CPF/CNPJ is required for the next step. Payer probably was not created successfully.');

    $api_url = config('openfinance.url') . 'account';

    $account = [
        'bankCode' => $body['bankCode'],
        'agency' => $body['agency'],
        'agencyDigit' => $body['agencyDigit'] ?? '',
        'accountNumber' => $body['accountNumber'],
        'accountNumberDigit' => $body['accountNumberDigit'] ?? '',
        'statementActived' => true,
    ];
    Log::debug('CREATE ACCOUNT **PAYLOAD: ', [$account]);

    $response = Http::withHeaders($headers)->acceptJson()->post($api_url, [$account]);

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

// Passo 3: gerar protocolo de extrato via Open Finance.
// Endpoint remoto: POST /statement/openfinance.
// Esta etapa não retorna as transações; retorna uniqueId/status PROCESSING.
// Guarde o uniqueId para consultar o resultado no passo 4.
// Rate limit informado pela Tecnospeed: 1 requisição de sucesso a cada 6 horas.
Route::post('/statement-openfinance-test', function (Request $request) {
    Log::debug('CREATE STATEMENT PROTOCOL **REQUEST: ', $request->except(['_token']));

    $today = $request->boolean('today');

    $validator = Validator::make($request->all(), [
        'cpfCnpj' => 'required|string',
        'accountHash' => 'required|string',
        'dateStart' => [$today ? 'nullable' : 'required', 'date_format:Y-m-d'],
        'dateEnd' => [$today ? 'nullable' : 'required', 'date_format:Y-m-d', 'after_or_equal:dateStart'],
        'today' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        Log::debug('CREATE STATEMENT PROTOCOL **VALIDATION ERRORS: ', $validator->errors()->toArray());

        return redirect()->back()->withErrors($validator)->withInput();
    }

    $body = $validator->validated();

    $headers = [
        'tokensh' => config('openfinance.tokensh'),
        'cnpjsh' => config('openfinance.cnpjsh'),
        'payercpfcnpj' => $body['cpfCnpj'],
    ];

    $payload = [
        'today' => $today,
        'accountHash' => $body['accountHash'],
    ];

    if (! $today) {
        $payload['dateStart'] = $body['dateStart'];
        $payload['dateEnd'] = $body['dateEnd'];
    }

    $apiUrl = config('openfinance.url') . 'statement/openfinance';

    Log::debug('CREATE STATEMENT PROTOCOL **PAYLOAD: ', $payload);

    $response = Http::withHeaders($headers)->acceptJson()->post($apiUrl, $payload);

    $responseData = $response->json() ?? $response->body();

    Log::debug('CREATE STATEMENT PROTOCOL **RESPONSE: ', is_array($responseData) ? $responseData : ['raw' => $responseData]);

    return redirect()->back()->with(['response_statement_protocol' => $responseData]);
})->name('openfinance.create_statement_protocol');

// Passo 4: consultar o resultado do processamento do extrato pelo uniqueId.
// Endpoint remoto: GET /statement/openfinance/{uniqueId}.
// Quando o processamento terminar, a resposta traz statement, transaction, transactionDuplicated e balance.
// Se ainda houver erro/autorizacao pendente, a Tecnospeed retorna os erros documentados.
// Rate limit informado pela Tecnospeed: 3 requisições por minuto; cache de 1 hora.
Route::post('/statement-openfinance-result-test', function (Request $request) {
    Log::debug('GET STATEMENT RESULT **REQUEST: ', $request->except(['_token']));

    $validator = Validator::make($request->all(), [
        'cpfCnpj' => 'required|string',
        'uniqueId' => 'required|string',
    ]);

    if ($validator->fails()) {
        Log::debug('GET STATEMENT RESULT **VALIDATION ERRORS: ', $validator->errors()->toArray());

        return redirect()->back()->withErrors($validator)->withInput();
    }

    $body = $validator->validated();

    $headers = [
        'tokensh' => config('openfinance.tokensh'),
        'cnpjsh' => config('openfinance.cnpjsh'),
        'payercpfcnpj' => $body['cpfCnpj'],
    ];

    $apiUrl = config('openfinance.url') . 'statement/openfinance/' . rawurlencode($body['uniqueId']);

    Log::debug('GET STATEMENT RESULT **URL: ', ['url' => $apiUrl]);

    $response = Http::withHeaders($headers)->acceptJson()->get($apiUrl);

    $responseData = $response->json() ?? $response->body();

    Log::debug('GET STATEMENT RESULT **RESPONSE: ', [
        'status' => $response->status(),
        'body' => $responseData,
    ]);

    return redirect()->back()->with(['response_statement_result' => $responseData]);
})->name('openfinance.get_statement_result');
