<?php

namespace App\Services\Assistant;

use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class AssistantContextService
{
    public function build(User $user, string $phoneNormalized, int $recentTransactionsLimit): array
    {
        $accounts = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        $budgets = FinancialBudget::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $transactions = FinancialTransaction::query()
            ->where('user_id', $user->id)
            ->with('account:id,name,marketing_name,number_last4')
            ->latest('occurred_at')
            ->limit(max(1, $recentTransactionsLimit))
            ->get();

        $defaultCurrency = $accounts->pluck('currency')->filter()->first()
            ?? $budgets->pluck('currency')->filter()->first()
            ?? 'BRL';

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'phoneNormalized' => $phoneNormalized,
            ],
            'supportedIntents' => array_values(config('assistant.intents', [])),
            'defaults' => [
                'currency' => $defaultCurrency,
                'timezone' => config('app.timezone'),
                'today' => Carbon::now(config('app.timezone'))->toDateString(),
                'defaultAccountId' => $accounts->count() === 1 ? $accounts->first()?->id : null,
            ],
            'categories' => [
                'income' => array_values(config('assistant.categories.income', [])),
                'expense' => array_values(config('assistant.categories.expense', [])),
                'budget' => array_values(config('assistant.categories.budget', [])),
            ],
            'accounts' => $accounts->map(fn (FinancialAccount $account) => $this->serializeAccount($account))->values()->all(),
            'budgets' => $budgets->map(fn (FinancialBudget $budget) => $this->serializeBudget($budget))->values()->all(),
            'recentTransactions' => $transactions->map(fn (FinancialTransaction $transaction) => $this->serializeTransaction($transaction))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAccount(FinancialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'marketingName' => $account->marketing_name,
            'type' => $account->type,
            'subtype' => $account->subtype,
            'owner' => $account->owner,
            'last4' => $account->number_last4,
            'balance' => $this->toFloat($account->balance),
            'currency' => $account->currency,
            'creditData' => $account->credit_data,
            'data' => $account->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBudget(FinancialBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'targetAmount' => $this->toFloat($budget->target_amount),
            'currentAmount' => $this->toFloat($budget->current_amount),
            'remainingAmount' => max(0, $this->toFloat($budget->target_amount) - $this->toFloat($budget->current_amount)),
            'icon' => $budget->icon,
            'category' => $budget->category,
            'currency' => $budget->currency,
            'data' => $budget->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(FinancialTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'accountId' => $transaction->account_id,
            'accountName' => $transaction->account?->name,
            'accountMarketingName' => $transaction->account?->marketing_name,
            'accountLast4' => $transaction->account?->number_last4,
            'type' => $transaction->type,
            'amount' => $this->toFloat($transaction->amount),
            'currency' => $transaction->currency,
            'occurredAt' => $transaction->occurred_at?->toIso8601String(),
            'description' => $transaction->description,
            'merchant' => $transaction->merchant,
            'category' => $transaction->category,
            'data' => $transaction->data,
        ];
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
