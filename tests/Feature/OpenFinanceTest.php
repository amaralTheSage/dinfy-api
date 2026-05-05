<?php

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\UserAddress;
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
        'openfinance_statement_status' => 'WAITING_AUTHORIZATION',
        'number_last4' => '5678',
    ]);
    $this->assertDatabaseHas('user_addresses', [
        'user_id' => $user->id,
        'type' => 'openfinance',
        'zipcode' => '87020025',
        'neighborhood' => 'Centro',
        'address_number' => '123',
        'state' => 'PR',
        'city' => 'Maringa',
    ]);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://tecnospeed.test/api/v1/account'
            && $request->hasHeader('payercpfcnpj', '52998224725')
            && $request[0]['statementActived'] === true
            && $request[0]['accountType'] === 'bank'
            && $request[0]['accountPayment'] === false;
    });
});

it('creates a credit card Open Finance account when requested', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/payer' => Http::response(['ok' => true], 201),
        'https://tecnospeed.test/api/v1/account' => Http::response([
            'accounts' => [[
                'bankCode' => '260',
                'accountHash' => 'credit-card-hash-123',
                'openfinanceLink' => 'https://tecnospeed.test/gui/card',
            ]],
        ], 201),
    ]);

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
        'accountType' => 'credit_card',
        'bankCode' => '260',
        'bankName' => 'Nubank',
        'agency' => '1',
        'accountNumber' => '12345678',
        'accountNumberDigit' => '9',
    ])
        ->assertCreated()
        ->assertJsonPath('account.accountType', 'credit_card');

    $this->assertDatabaseHas('financial_accounts', [
        'user_id' => $user->id,
        'type' => 'credit_card',
        'subtype' => 'openfinance',
        'openfinance_account_hash' => 'credit-card-hash-123',
    ]);

    expect(data_get(FinancialAccount::query()->first()?->data, 'openfinance.accountType'))
        ->toBe('credit_card');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://tecnospeed.test/api/v1/account'
            && $request[0]['accountType'] === 'credit_card'
            && $request[0]['statementActived'] === true;
    });
});

it('returns saved Open Finance address and connected accounts for the home screen', function () {
    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
        'created_at' => now()->subMonthsNoOverflow(3),
    ]);
    UserAddress::query()->create([
        'user_id' => $user->id,
        'type' => 'openfinance',
        'zipcode' => '87020025',
        'street' => 'Rua Teste',
        'neighborhood' => 'Centro',
        'address_number' => '123',
        'address_complement' => 'Sala 4',
        'state' => 'PR',
        'city' => 'Maringa',
    ]);
    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Nubank',
        'marketing_name' => 'Nubank',
        'number_last4' => '5678',
        'balance' => 0,
        'currency' => 'BRL',
        'data' => ['openfinance' => ['bankCode' => '260', 'bankName' => 'Nubank']],
        'openfinance_account_hash' => 'nubank-hash-123',
        'openfinance_status' => 'authorization_pending',
    ]);
    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Conta antiga',
        'number_last4' => '1111',
        'balance' => 0,
        'currency' => 'BRL',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/openfinance')
        ->assertOk()
        ->assertJsonPath('cpfCnpj', '52998224725')
        ->assertJsonPath('address.zipcode', '87020025')
        ->assertJsonPath('address.addressNumber', '123')
        ->assertJsonFragment([
            'bankCode' => '260',
            'bankName' => 'Nubank',
            'numberLast4' => '5678',
        ])
        ->assertJsonCount(2, 'accounts');
});

it('disconnects an Open Finance account without deleting imported transactions', function () {
    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
    ]);
    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Nubank',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_account_hash' => 'nubank-hash-123',
        'openfinance_status' => 'active',
        'openfinance_statement_status' => 'COMPLETED',
    ]);
    $transaction = FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'provider' => 'openfinance',
        'provider_transaction_id' => 'of-1',
        'type' => 'DEBIT',
        'amount' => 19.9,
        'currency' => 'BRL',
        'occurred_at' => '2026-05-04 12:00:00',
        'description' => 'Open Finance',
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/openfinance/accounts/{$account->id}/disconnect")
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertSoftDeleted('financial_accounts', ['id' => $account->id]);
    $this->assertDatabaseHas('financial_transactions', ['id' => $transaction->id]);
});

it('blocks generic account routes from mutating Open Finance accounts', function () {
    $user = User::factory()->create();
    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Nubank',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_account_hash' => 'nubank-hash-123',
    ]);

    Sanctum::actingAs($user);

    $this->putJson("/api/accounts/{$account->id}", [
        'name' => 'Outro nome',
    ])->assertUnprocessable()->assertJsonValidationErrors('account');

    $this->deleteJson("/api/accounts/{$account->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    $this->postJson('/api/accounts', [
        'id' => $account->id,
        'type' => 'bank',
        'name' => 'Outro nome',
    ])->assertUnprocessable()->assertJsonValidationErrors('account');

    $this->postJson('/api/accounts', [
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Conta falsa',
    ])->assertUnprocessable()->assertJsonValidationErrors('account');
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

it('refreshes pending authorization and requests a statement protocol from cron sync', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/account/hash-account-123' => Http::response([
            'accounts' => [[
                'openfinanceId' => 'openfinance-id-123',
                'openfinanceLink' => 'https://tecnospeed.test/gui/authorize',
            ]],
        ], 200),
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
        'openfinance_account_hash' => 'hash-account-123',
        'openfinance_status' => 'authorization_pending',
        'openfinance_statement_status' => 'WAITING_AUTHORIZATION',
    ]);

    $stats = app(OpenFinanceStatementSyncService::class)->handle();

    expect($stats['authorization_checked'])->toBe(1);
    expect($stats['protocols_requested'])->toBe(1);

    $account->refresh();

    expect($account->openfinance_id)->toBe('openfinance-id-123');
    expect($account->openfinance_status)->toBe('active');
    expect($account->openfinance_statement_status)->toBe('PROCESSING');
    expect($account->openfinance_last_statement_unique_id)->toBe('statement-123');
});

it('requests statement protocols in the background for due active accounts', function () {
    Http::fake([
        'https://tecnospeed.test/api/v1/statement/openfinance' => Http::response([
            'uniqueId' => 'statement-123',
            'status' => 'PROCESSING',
        ], 201),
    ]);

    $userCreatedAt = now()->subMonthsNoOverflow(3);
    $expectedDateStart = $userCreatedAt->copy()->subMonthNoOverflow()->toDateString();

    $user = User::factory()->create([
        'cpf_cnpj' => '52998224725',
        'created_at' => $userCreatedAt,
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

    Http::assertSent(function (HttpRequest $request) use ($expectedDateStart): bool {
        return $request->url() === 'https://tecnospeed.test/api/v1/statement/openfinance'
            && $request->hasHeader('payercpfcnpj', '52998224725')
            && $request['accountHash'] === 'hash-account-123'
            && $request['dateStart'] === $expectedDateStart
            && $request['dateEnd'] === now()->toDateString();
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
