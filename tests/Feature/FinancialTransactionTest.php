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
