<?php

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\OpenFinance\OpenFinanceStatementSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('openfinance.url', 'https://tecnospeed.test/api/v1/');
    config()->set('openfinance.tokensh', 'token-sh-test');
    config()->set('openfinance.cnpjsh', '01001001000113');
});

it('creates the payer and account through TecnoSpeed and stores the account hash', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/payer' => Http::response(['ok' => true], 201),
        'https://tecnospeed.test/api/v1/account' => Http::response([
            'accounts' => [[
                'bankCode' => '001',
                'accountHash' => 'hash-account-123',
                'openfinanceLink' => 'https://tecnospeed.test/gui/authorize',
            ]],
        ], 201),
    ]);

    $user = User::factory()->create([
        'name' => 'Gabriel',
        'cpf_cnpj' => '52998224725',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/openfinance/connect', [
        'zipcode' => '87020025',
        'street' => 'Rua Teste',
        'neighborhood' => 'Centro',
        'addressNumber' => '123',
        'state' => 'PR',
        'city' => 'Maringa',
        'bankCode' => '001',
        'bankName' => 'Banco do Brasil',
        'agency' => '1234',
        'accountNumber' => '12345678',
        'accountNumberDigit' => '9',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('accountHash', 'hash-account-123')
        ->assertJsonPath('openfinanceLink', 'https://tecnospeed.test/gui/authorize')
        ->assertJsonPath('warning', 'A liberacao pelo banco pode levar ate 24 horas. Depois disso, as atualizacoes acontecem em segundo plano e nao sao imediatas.');

    $this->assertDatabaseHas('financial_accounts', [
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'openfinance_account_hash' => 'hash-account-123',
        'openfinance_status' => 'authorization_pending',
        'number_last4' => '5678',
    ]);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://tecnospeed.test/api/v1/account'
            && $request->hasHeader('payercpfcnpj', '52998224725')
            && $request[0]['statementActived'] === true
            && $request[0]['accountPayment'] === false;
    });
});

it('updates an existing payer when TecnoSpeed reports the payer already exists', function () {
    Http::fakeSequence()
        ->push(['errors' => ['internalCode' => 7632]], 422)
        ->push(['ok' => true], 200)
        ->push([
            'accounts' => [[
                'bankCode' => '260',
                'accountHash' => 'nubank-hash-123',
                'openfinanceLink' => 'https://tecnospeed.test/gui/nubank',
            ]],
        ], 201);

    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/openfinance/connect', [
        'zipcode' => '87020025',
        'neighborhood' => 'Centro',
        'addressNumber' => '123',
        'state' => 'PR',
        'city' => 'Maringa',
        'bankCode' => '260',
        'bankName' => 'Nubank',
        'agency' => '1',
        'accountNumber' => '12345678',
        'accountNumberDigit' => '9',
    ])->assertCreated();

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->method() === 'PUT'
            && $request->url() === 'https://tecnospeed.test/api/v1/payer'
            && $request->hasHeader('payercpfcnpj', '52998224725')
            && $request['statementActived'] === true;
    });
});

it('requires a valid user document before connecting Open Finance', function () {
    Http::fake();

    $user = User::factory()->create([
        'cpf_cnpj' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/openfinance/connect', [
        'cpfCnpj' => '111.111.111-11',
        'zipcode' => '87020025',
        'neighborhood' => 'Centro',
        'addressNumber' => '123',
        'state' => 'PR',
        'city' => 'Maringa',
        'bankCode' => '001',
        'agency' => '1234',
        'accountNumber' => '12345678',
        'accountNumberDigit' => '9',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cpfCnpj');

    Http::assertNothingSent();
});

it('requests statement protocols in the background for due active accounts', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/statement/openfinance' => Http::response([
            'uniqueId' => 'statement-123',
            'status' => 'PROCESSING',
        ], 201),
    ]);

    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
    ]);
    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'name' => 'Banco do Brasil',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_id' => 'openfinance-id-123',
        'openfinance_status' => 'active',
        'openfinance_account_hash' => 'hash-account-123',
    ]);

    $stats = app(OpenFinanceStatementSyncService::class)->handle();

    expect($stats['protocols_requested'])->toBe(1);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://tecnospeed.test/api/v1/statement/openfinance'
            && $request->hasHeader('payercpfcnpj', '52998224725')
            && $request['accountHash'] === 'hash-account-123'
            && filled($request['dateStart'])
            && filled($request['dateEnd']);
    });

    $account->refresh();

    expect($account->openfinance_last_statement_unique_id)->toBe('statement-123');
    expect($account->openfinance_statement_status)->toBe('PROCESSING');
    expect($account->openfinance_next_statement_at)->not->toBeNull();
});

it('does not request more than one statement protocol per hour per account', function () {
    Http::fake();

    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
    ]);

    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'name' => 'Banco do Brasil',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_id' => 'openfinance-id-123',
        'openfinance_status' => 'active',
        'openfinance_account_hash' => 'hash-account-123',
        'openfinance_last_statement_requested_at' => now()->subMinutes(30),
        'openfinance_next_statement_at' => now(),
    ]);

    $stats = app(OpenFinanceStatementSyncService::class)->handle();

    expect($stats['protocols_requested'])->toBe(0);

    Http::assertNothingSent();
});

it('imports only new statement transactions and ignores TecnoSpeed duplicated transactions', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/statement/openfinance/statement-123' => Http::response([
            'statement' => [
                'uniqueId' => 'statement-123',
                'bankCode' => '208',
                'totalTransactions' => '2',
                'origin' => 'OPENFINANCE',
                'accountHash' => 'hash-account-123',
            ],
            'transaction' => [
                'credit' => [[
                    'transactionId' => 'credit-transaction-1',
                    'transactionType' => 'credit',
                    'code' => 'DIGITALSERVICES',
                    'amount' => '18.99',
                    'date' => '2026-05-03',
                    'sequence' => 1,
                    'description' => 'DL*GOOGLEYouTube',
                    'fitid' => null,
                    'name' => 'John Doe',
                    'category' => 'Digital services',
                ]],
                'debit' => [[
                    'transactionId' => 'debit-transaction-1',
                    'transactionType' => 'debit',
                    'code' => 'SERVICES',
                    'amount' => '100.00',
                    'date' => '2026-05-04',
                    'sequence' => 1,
                    'description' => 'Pagamento de boleto',
                    'fitid' => null,
                    'name' => 'PLUGGY',
                    'category' => 'Services',
                ]],
            ],
            'transactionDuplicated' => [
                'credit' => [[
                    'transactionId' => 'duplicated-credit-1',
                    'transactionType' => 'credit',
                    'amount' => '10.00',
                    'date' => '2026-05-01',
                    'description' => 'Duplicada',
                ]],
                'debit' => [],
            ],
            'balance' => [
                'inicial' => ['date' => '2026-05-03', 'balance' => '100.00'],
                'final' => ['date' => '2026-05-04', 'balance' => '18.99'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
    ]);
    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'name' => 'BTG Pactual',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_id' => 'openfinance-id-123',
        'openfinance_status' => 'active',
        'openfinance_account_hash' => 'hash-account-123',
        'openfinance_last_statement_unique_id' => 'statement-123',
        'openfinance_statement_status' => 'PROCESSING',
        'openfinance_last_statement_requested_at' => now()->subMinutes(61),
    ]);

    $stats = app(OpenFinanceStatementSyncService::class)->handle();

    expect($stats['protocols_checked'])->toBe(1);
    expect($stats['transactions_imported'])->toBe(2);

    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $account->id,
        'provider' => 'openfinance',
        'provider_transaction_id' => 'credit-transaction-1',
        'type' => 'CREDIT',
        'amount' => 18.99,
    ]);
    $this->assertDatabaseHas('financial_transactions', [
        'account_id' => $account->id,
        'provider' => 'openfinance',
        'provider_transaction_id' => 'debit-transaction-1',
        'type' => 'DEBIT',
        'amount' => 100.00,
    ]);

    expect(FinancialTransaction::query()->where('provider', 'openfinance')->count())->toBe(2);

    $account->refresh();

    expect((float) $account->balance)->toBe(18.99);
    expect($account->openfinance_statement_status)->toBe('COMPLETED');
    expect(data_get($account->data, 'openfinance.lastStatement.duplicatedTransactionsIgnored'))->toBe(1);
});
