<?php

use App\Models\AssistantExecution;
use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('assistant.secret', 'super-secret');
    config()->set('assistant.secret_header', 'X-Assistant-Secret');
    config()->set('assistant.phone.default_country_code', '55');
});

it('returns assistant context for a whatsapp phone', function () {
    $user = User::factory()->create([
        'name' => 'Gabriel',
        'phone' => '(11) 99999-1234',
        'phone_normalized' => PhoneNormalizer::normalize('(11) 99999-1234'),
    ]);

    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CREDIT',
        'subtype' => 'CREDIT_CARD',
        'name' => 'Nubank Ultravioleta',
        'marketing_name' => 'Nubank',
        'number_last4' => '1234',
        'balance' => 420.50,
        'currency' => 'BRL',
        'credit_data' => ['limit' => 1000],
    ]);

    FinancialBudget::query()->create([
        'user_id' => $user->id,
        'name' => 'Mercado',
        'target_amount' => 600,
        'current_amount' => 120,
        'category' => 'Grocery',
        'currency' => 'BRL',
    ]);

    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'DEBIT',
        'amount' => 35.90,
        'currency' => 'BRL',
        'occurred_at' => Carbon::parse('2026-03-19 13:00:00'),
        'description' => 'Padaria',
        'merchant' => 'Padaria',
        'category' => 'Food & drink',
    ]);

    $response = $this->postJson('/api/assistant/context', [
        'phone' => '+55 (11) 99999-1234',
    ], [
        'X-Assistant-Secret' => 'super-secret',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('accounts.0.id', $account->id)
        ->assertJsonPath('budgets.0.category', 'Grocery')
        ->assertJsonPath('recentTransactions.0.description', 'Padaria')
        ->assertJsonPath('defaults.defaultAccountId', $account->id);
});

it('matches a brazilian mobile phone when the webhook omits the ninth digit', function () {
    $user = User::factory()->create([
        'name' => 'Gabriel',
        'phone' => '(53) 98124-8282',
        'phone_normalized' => PhoneNormalizer::normalize('(53) 98124-8282'),
    ]);

    $response = $this->postJson('/api/assistant/context', [
        'phone' => '555381248282',
    ], [
        'X-Assistant-Secret' => 'super-secret',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.phone', '(53) 98124-8282');
});

it('creates a transaction through the assistant endpoint', function () {
    $user = User::factory()->create([
        'phone' => '11999991234',
        'phone_normalized' => PhoneNormalizer::normalize('11999991234'),
    ]);

    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CREDIT',
        'subtype' => 'CREDIT_CARD',
        'name' => 'Nubank Mastercard',
        'marketing_name' => 'Nubank',
        'number_last4' => '1234',
        'balance' => 100,
        'currency' => 'BRL',
    ]);

    $response = $this->postJson('/api/assistant/execute', [
        'phone' => '+55 11 99999-1234',
        'idempotencyKey' => 'wamid-1',
        'intent' => 'create_transaction',
        'parameters' => [
            'accountName' => 'Nubank',
            'type' => 'DEBIT',
            'amount' => 32,
            'description' => 'Pizza',
            'merchant' => 'Pizzaria',
            'category' => 'Food & drink',
            'occurredAt' => '2026-03-20',
        ],
        'metadata' => [
            'channel' => 'whatsapp',
            'messageId' => 'wamid-1',
            'messageText' => 'Comprei uma pizza de 32 reais',
        ],
    ], [
        'X-Assistant-Secret' => 'super-secret',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('intent', 'create_transaction')
        ->assertJsonPath('transaction.accountId', $account->id)
        ->assertJsonPath('transaction.category', 'Food & drink')
        ->assertJsonPath('replayed', false);

    $this->assertDatabaseCount('financial_transactions', 1);
    $this->assertDatabaseHas('assistant_executions', [
        'user_id' => $user->id,
        'idempotency_key' => 'wamid-1',
        'intent' => 'create_transaction',
        'status' => 'completed',
    ]);
});

it('replays the same assistant execution when the idempotency key repeats', function () {
    $user = User::factory()->create([
        'phone' => '11999991234',
        'phone_normalized' => PhoneNormalizer::normalize('11999991234'),
    ]);

    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CHECKING',
        'name' => 'Conta Principal',
        'balance' => 500,
        'currency' => 'BRL',
    ]);

    $payload = [
        'phone' => '11999991234',
        'idempotencyKey' => 'wamid-repeat',
        'intent' => 'create_transaction',
        'parameters' => [
            'type' => 'DEBIT',
            'amount' => 50,
            'description' => 'Mercado',
            'category' => 'Grocery',
            'occurredAt' => '2026-03-20',
        ],
    ];

    $headers = ['X-Assistant-Secret' => 'super-secret'];

    $firstResponse = $this->postJson('/api/assistant/execute', $payload, $headers);
    $secondResponse = $this->postJson('/api/assistant/execute', $payload, $headers);

    $firstResponse->assertOk()->assertJsonPath('replayed', false);
    $secondResponse->assertOk()->assertJsonPath('replayed', true);

    expect(FinancialTransaction::query()->count())->toBe(1);
    expect(AssistantExecution::query()->count())->toBe(1);
});

it('returns consolidated balances through the assistant endpoint', function () {
    $user = User::factory()->create([
        'phone' => '11988887777',
        'phone_normalized' => PhoneNormalizer::normalize('11988887777'),
    ]);

    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CHECKING',
        'name' => 'Conta Corrente',
        'balance' => 1500,
        'currency' => 'BRL',
    ]);

    FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'SAVINGS',
        'name' => 'Reserva',
        'balance' => 250,
        'currency' => 'BRL',
    ]);

    $response = $this->postJson('/api/assistant/execute', [
        'phone' => '+55 11 98888-7777',
        'idempotencyKey' => 'wamid-balance',
        'intent' => 'get_balance',
        'parameters' => [],
    ], [
        'X-Assistant-Secret' => 'super-secret',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('intent', 'get_balance')
        ->assertJsonPath('balances.0.total', 1750)
        ->assertJsonCount(2, 'accounts');
});
