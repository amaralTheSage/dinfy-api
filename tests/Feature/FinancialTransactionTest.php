<?php

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('updates a transaction and can clear its account', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CHECKING',
        'name' => 'Conta Principal',
        'balance' => 500,
        'currency' => 'BRL',
    ]);

    $transaction = FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'DEBIT',
        'amount' => 25,
        'currency' => 'BRL',
        'occurred_at' => '2026-04-10 12:00:00',
        'description' => 'Almoco',
        'category' => 'Food & drink',
        'data' => ['source' => 'manual'],
    ]);

    $response = $this->putJson("/api/transactions/{$transaction->id}", [
        'accountId' => null,
        'type' => 'CREDIT',
        'amount' => 43,
        'occurredAt' => '2026-04-13',
        'description' => 'Venda de pizza',
        'merchant' => 'Pizzaria',
        'category' => 'Business',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('id', $transaction->id)
        ->assertJsonPath('account_id', null)
        ->assertJsonPath('type', 'CREDIT')
        ->assertJsonPath('amount', '43.00')
        ->assertJsonPath('description', 'Venda de pizza')
        ->assertJsonPath('merchant', 'Pizzaria')
        ->assertJsonPath('category', 'Business');

    $this->assertDatabaseHas('financial_transactions', [
        'id' => $transaction->id,
        'account_id' => null,
        'type' => 'CREDIT',
        'amount' => '43.00',
        'description' => 'Venda de pizza',
        'merchant' => 'Pizzaria',
        'category' => 'Business',
    ]);
});

it('deletes a transaction', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $account = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'CHECKING',
        'name' => 'Conta Principal',
        'balance' => 500,
        'currency' => 'BRL',
    ]);

    $transaction = FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'DEBIT',
        'amount' => 19.9,
        'currency' => 'BRL',
        'occurred_at' => '2026-04-11 12:00:00',
        'description' => 'Cafe',
        'category' => 'Food & drink',
    ]);

    $this->deleteJson("/api/transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(FinancialTransaction::query()->count())->toBe(0);
    expect(FinancialTransaction::withTrashed()->count())->toBe(1);
});

it('filters transactions by account for the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $openFinanceAccount = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'bank',
        'subtype' => 'openfinance',
        'name' => 'Nubank',
        'balance' => 0,
        'currency' => 'BRL',
        'openfinance_account_hash' => 'hash-account-123',
    ]);
    $manualAccount = FinancialAccount::query()->create([
        'user_id' => $user->id,
        'type' => 'cash',
        'name' => 'Dinheiro',
        'balance' => 0,
        'currency' => 'BRL',
    ]);

    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $openFinanceAccount->id,
        'provider' => 'openfinance',
        'provider_transaction_id' => 'of-1',
        'type' => 'DEBIT',
        'amount' => 19.9,
        'currency' => 'BRL',
        'occurred_at' => '2026-04-11 12:00:00',
        'description' => 'Open Finance',
    ]);
    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $manualAccount->id,
        'type' => 'DEBIT',
        'amount' => 10,
        'currency' => 'BRL',
        'occurred_at' => '2026-04-12 12:00:00',
        'description' => 'Manual',
    ]);

    $this->getJson("/api/transactions?accountId={$openFinanceAccount->id}&per_page=50")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider', 'openfinance')
        ->assertJsonPath('data.0.account.id', $openFinanceAccount->id);
});

it('returns monthly transaction totals from all transactions in the month', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);

    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'CREDIT',
        'amount' => 200,
        'currency' => 'BRL',
        'occurred_at' => '2026-05-01 12:00:00',
        'description' => 'Receita',
    ]);
    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'DEBIT',
        'amount' => 75.5,
        'currency' => 'BRL',
        'occurred_at' => '2026-05-20 12:00:00',
        'description' => 'Despesa',
    ]);
    FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'DEBIT',
        'amount' => 30,
        'currency' => 'BRL',
        'occurred_at' => '2026-04-30 12:00:00',
        'description' => 'Mes anterior',
    ]);
    FinancialTransaction::query()->create([
        'user_id' => $otherUser->id,
        'type' => 'CREDIT',
        'amount' => 999,
        'currency' => 'BRL',
        'occurred_at' => '2026-05-10 12:00:00',
        'description' => 'Outro usuario',
    ]);

    $this->getJson('/api/transactions/summary?month=2026-05')
        ->assertOk()
        ->assertJsonPath('month', '2026-05')
        ->assertJsonPath('income', 200)
        ->assertJsonPath('expenses', 75.5)
        ->assertJsonPath('balance', 124.5)
        ->assertJsonPath('currency', 'BRL');
});

it('does not allow imported Open Finance transactions to be edited or deleted manually', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $transaction = FinancialTransaction::query()->create([
        'user_id' => $user->id,
        'provider' => 'openfinance',
        'provider_transaction_id' => 'of-1',
        'type' => 'DEBIT',
        'amount' => 19.9,
        'currency' => 'BRL',
        'occurred_at' => '2026-05-04 12:00:00',
        'description' => 'Open Finance',
    ]);

    $this->putJson("/api/transactions/{$transaction->id}", [
        'type' => 'CREDIT',
        'amount' => 10,
        'occurredAt' => '2026-05-05',
    ])->assertUnprocessable()->assertJsonValidationErrors('transaction');

    $this->deleteJson("/api/transactions/{$transaction->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transaction');

    $this->assertDatabaseHas('financial_transactions', [
        'id' => $transaction->id,
        'type' => 'DEBIT',
        'amount' => '19.90',
        'deleted_at' => null,
    ]);
});
