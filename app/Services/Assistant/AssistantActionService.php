<?php

namespace App\Services\Assistant;

use App\Models\FinancialAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssistantActionService
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function execute(User $user, string $intent, array $parameters = [], array $metadata = []): array
    {
        return match ($intent) {
            'create_transaction' => $this->createTransaction($user, $parameters, $metadata),
            'create_budget' => $this->createBudget($user, $parameters, $metadata),
            'get_balance' => $this->getBalance($user, $parameters),
            'get_budget_status' => $this->getBudgetStatus($user, $parameters),
            'list_recent_transactions' => $this->listRecentTransactions($user, $parameters),
            default => throw ValidationException::withMessages([
                'intent' => ['A intenção informada não é suportada por este endpoint.'],
            ]),
        };
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function createTransaction(User $user, array $parameters, array $metadata): array
    {
        $validated = Validator::make($parameters, [
            'accountId' => ['nullable', 'uuid'],
            'accountName' => ['nullable', 'string', 'max:255'],
            'accountLast4' => ['nullable', 'string', 'max:4'],
            'type' => ['required', 'string', Rule::in(['DEBIT', 'CREDIT'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'occurredAt' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $account = $this->resolveTransactionAccount($user, $validated);

        $type = strtoupper((string) $validated['type']);
        $category = $this->normalizeCategory(
            $validated['category'] ?? null,
            $type === 'CREDIT' ? 'income' : 'expense',
        );
        $merchant = $this->nullableTrimmed($validated['merchant'] ?? null);
        $description = $this->nullableTrimmed($validated['description'] ?? null)
            ?? $merchant
            ?? $category;

        $transaction = new FinancialTransaction();
        $transaction->user_id = $user->id;
        $transaction->account_id = $account?->id;
        $transaction->type = $type;
        $transaction->amount = $validated['amount'];
        $transaction->currency = strtoupper((string) ($validated['currency'] ?? $account?->currency ?? 'BRL'));
        $transaction->occurred_at = Carbon::parse($validated['occurredAt'] ?? now());
        $transaction->description = $description;
        $transaction->merchant = $merchant;
        $transaction->category = $category;
        $transaction->data = array_filter([
            'source' => 'assistant',
            'channel' => $metadata['channel'] ?? 'n8n',
            'messageId' => $metadata['messageId'] ?? null,
            'messageText' => $metadata['messageText'] ?? null,
        ], fn (mixed $value) => $value !== null && $value !== '');
        $transaction->save();

        return [
            'intent' => 'create_transaction',
            'status' => 'completed',
            'summary' => sprintf(
                '%s de %s criada%s.',
                $type === 'CREDIT' ? 'Receita' : 'Despesa',
                $this->formatMoney((float) $transaction->amount, $transaction->currency),
                $account?->name ? ' na conta '.$account->name : ''
            ),
            'transaction' => $this->serializeTransaction($transaction, $account),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function createBudget(User $user, array $parameters, array $metadata): array
    {
        $validated = Validator::make($parameters, [
            'name' => ['required', 'string', 'max:255'],
            'targetAmount' => ['required', 'numeric', 'gt:0'],
            'currentAmount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'icon' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $category = $this->normalizeCategory($validated['category'] ?? null, 'budget');

        $budget = new FinancialBudget();
        $budget->user_id = $user->id;
        $budget->name = trim((string) $validated['name']);
        $budget->target_amount = $validated['targetAmount'];
        $budget->current_amount = $validated['currentAmount'] ?? 0;
        $budget->currency = strtoupper((string) ($validated['currency'] ?? 'BRL'));
        $budget->category = $category;
        $budget->icon = $this->nullableTrimmed($validated['icon'] ?? null) ?? $category;
        $budget->data = array_filter([
            'source' => 'assistant',
            'channel' => $metadata['channel'] ?? 'n8n',
            'messageId' => $metadata['messageId'] ?? null,
            'messageText' => $metadata['messageText'] ?? null,
        ], fn (mixed $value) => $value !== null && $value !== '');
        $budget->save();

        return [
            'intent' => 'create_budget',
            'status' => 'completed',
            'summary' => sprintf(
                'Orçamento "%s" criado com meta de %s.',
                $budget->name,
                $this->formatMoney((float) $budget->target_amount, $budget->currency),
            ),
            'budget' => $this->serializeBudget($budget),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function getBalance(User $user, array $parameters): array
    {
        $validated = Validator::make($parameters, [
            'accountId' => ['nullable', 'uuid'],
            'accountName' => ['nullable', 'string', 'max:255'],
            'accountLast4' => ['nullable', 'string', 'max:4'],
        ])->validate();

        $hasSpecificAccount = !empty($validated['accountId'])
            || !empty($validated['accountName'])
            || !empty($validated['accountLast4']);

        if ($hasSpecificAccount) {
            $account = $this->resolveAccount(
                $user,
                $validated['accountId'] ?? null,
                $validated['accountName'] ?? null,
                $validated['accountLast4'] ?? null,
            );

            return [
                'intent' => 'get_balance',
                'status' => 'completed',
                'summary' => sprintf(
                    'Saldo da conta %s: %s.',
                    $account->name,
                    $this->formatMoney((float) $account->balance, $account->currency),
                ),
                'balances' => [
                    [
                        'currency' => $account->currency,
                        'total' => (float) $account->balance,
                    ],
                ],
                'accounts' => [
                    $this->serializeAccount($account),
                ],
            ];
        }

        $accounts = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        $balances = $accounts
            ->groupBy(fn (FinancialAccount $account) => $account->currency ?: 'BRL')
            ->map(fn (Collection $group, string $currency) => [
                'currency' => $currency,
                'total' => round($group->sum(fn (FinancialAccount $account) => (float) $account->balance), 2),
            ])
            ->values()
            ->all();

        $primaryBalance = $balances[0] ?? ['currency' => 'BRL', 'total' => 0];

        return [
            'intent' => 'get_balance',
            'status' => 'completed',
            'summary' => sprintf(
                'Saldo consolidado: %s em %d conta(s).',
                $this->formatMoney((float) $primaryBalance['total'], (string) $primaryBalance['currency']),
                $accounts->count(),
            ),
            'balances' => $balances,
            'accounts' => $accounts->map(fn (FinancialAccount $account) => $this->serializeAccount($account))->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function getBudgetStatus(User $user, array $parameters): array
    {
        $validated = Validator::make($parameters, [
            'budgetId' => ['nullable', 'uuid'],
            'budgetName' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $query = FinancialBudget::query()
            ->where('user_id', $user->id)
            ->latest();

        if (!empty($validated['budgetId'])) {
            $query->where('id', $validated['budgetId']);
        }

        $budgets = $query->get();

        if (!empty($validated['budgetName'])) {
            $budgets = $budgets->filter(function (FinancialBudget $budget) use ($validated): bool {
                return $this->normalizeText($budget->name) === $this->normalizeText((string) $validated['budgetName'])
                    || str_contains($this->normalizeText($budget->name), $this->normalizeText((string) $validated['budgetName']));
            })->values();
        }

        if (!empty($validated['category'])) {
            $category = $this->normalizeCategory($validated['category'], 'budget') ?? trim((string) $validated['category']);
            $budgets = $budgets->filter(function (FinancialBudget $budget) use ($category): bool {
                return $this->normalizeText((string) $budget->category) === $this->normalizeText($category);
            })->values();
        }

        return [
            'intent' => 'get_budget_status',
            'status' => 'completed',
            'summary' => $budgets->isEmpty()
                ? 'Nenhum orçamento correspondente foi encontrado.'
                : sprintf('Foram encontrados %d orçamento(s).', $budgets->count()),
            'budgets' => $budgets->map(fn (FinancialBudget $budget) => $this->serializeBudget($budget))->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function listRecentTransactions(User $user, array $parameters): array
    {
        $validated = Validator::make($parameters, [
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'type' => ['nullable', 'string', Rule::in(['DEBIT', 'CREDIT'])],
            'accountId' => ['nullable', 'uuid'],
            'accountName' => ['nullable', 'string', 'max:255'],
            'accountLast4' => ['nullable', 'string', 'max:4'],
            'category' => ['nullable', 'string', 'max:100'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ])->validate();

        $query = FinancialTransaction::query()
            ->where('user_id', $user->id)
            ->with('account:id,name,marketing_name,number_last4')
            ->latest('occurred_at');

        if (!empty($validated['type'])) {
            $query->where('type', strtoupper((string) $validated['type']));
        }

        if (!empty($validated['startDate'])) {
            $query->where('occurred_at', '>=', Carbon::parse($validated['startDate'])->startOfDay());
        }

        if (!empty($validated['endDate'])) {
            $query->where('occurred_at', '<=', Carbon::parse($validated['endDate'])->endOfDay());
        }

        if (!empty($validated['category'])) {
            $query->where('category', $this->normalizeCategory(
                $validated['category'],
                !empty($validated['type']) && strtoupper((string) $validated['type']) === 'CREDIT' ? 'income' : 'expense',
            ) ?? trim((string) $validated['category']));
        }

        if (!empty($validated['accountId']) || !empty($validated['accountName']) || !empty($validated['accountLast4'])) {
            $account = $this->resolveAccount(
                $user,
                $validated['accountId'] ?? null,
                $validated['accountName'] ?? null,
                $validated['accountLast4'] ?? null,
            );
            $query->where('account_id', $account->id);
        }

        $limit = (int) ($validated['limit'] ?? 5);
        $transactions = $query->limit($limit)->get();

        return [
            'intent' => 'list_recent_transactions',
            'status' => 'completed',
            'summary' => $transactions->isEmpty()
                ? 'Nenhuma transação foi encontrada com esses filtros.'
                : sprintf('Foram encontradas %d transação(ões).', $transactions->count()),
            'transactions' => $transactions->map(fn (FinancialTransaction $transaction) => $this->serializeTransaction($transaction, $transaction->account))->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveTransactionAccount(User $user, array $parameters): ?FinancialAccount
    {
        if ($this->hasAccountFilters($parameters)) {
            return $this->resolveAccount(
                $user,
                $parameters['accountId'] ?? null,
                $parameters['accountName'] ?? null,
                $parameters['accountLast4'] ?? null,
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function hasAccountFilters(array $parameters): bool
    {
        return !empty($parameters['accountId'])
            || !empty($parameters['accountName'])
            || !empty($parameters['accountLast4']);
    }

    private function resolveAccount(
        User $user,
        ?string $accountId = null,
        ?string $accountName = null,
        ?string $accountLast4 = null,
    ): FinancialAccount {
        $accounts = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->get();

        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages([
                'parameters.accountId' => ['O usuário não possui contas cadastradas.'],
            ]);
        }

        if ($accountId) {
            $account = $accounts->firstWhere('id', $accountId);
            if ($account) {
                return $account;
            }
        }

        if ($accountLast4) {
            $matchingByLast4 = $accounts->filter(fn (FinancialAccount $account): bool => $account->number_last4 === trim($accountLast4))->values();
            if ($matchingByLast4->count() === 1) {
                return $matchingByLast4->firstOrFail();
            }
            if ($matchingByLast4->count() > 1) {
                throw ValidationException::withMessages([
                    'parameters.accountLast4' => ['Mais de uma conta corresponde ao final informado.'],
                ]);
            }
        }

        if ($accountName) {
            $needle = $this->normalizeText($accountName);

            $exact = $accounts->filter(function (FinancialAccount $account) use ($needle): bool {
                return $this->normalizeText($account->name) === $needle
                    || $this->normalizeText((string) $account->marketing_name) === $needle;
            })->values();

            if ($exact->count() === 1) {
                return $exact->firstOrFail();
            }

            if ($exact->count() > 1) {
                throw ValidationException::withMessages([
                    'parameters.accountName' => ['Mais de uma conta corresponde ao nome informado.'],
                ]);
            }

            $contains = $accounts->filter(function (FinancialAccount $account) use ($needle): bool {
                return str_contains($this->normalizeText($account->name), $needle)
                    || str_contains($this->normalizeText((string) $account->marketing_name), $needle);
            })->values();

            if ($contains->count() === 1) {
                return $contains->firstOrFail();
            }

            if ($contains->count() > 1) {
                throw ValidationException::withMessages([
                    'parameters.accountName' => ['O nome informado bate com várias contas. Informe o accountId ou o final da conta.'],
                ]);
            }
        }

        if ($accounts->count() === 1) {
            return $accounts->firstOrFail();
        }

        throw ValidationException::withMessages([
            'parameters.accountId' => ['Informe a conta usando accountId, accountName ou accountLast4.'],
        ]);
    }

    private function normalizeCategory(?string $value, string $group): ?string
    {
        $trimmed = $this->nullableTrimmed($value);

        if ($trimmed === null) {
            return null;
        }

        foreach ((array) config("assistant.categories.$group", []) as $knownCategory) {
            if ($this->normalizeText((string) $knownCategory) === $this->normalizeText($trimmed)) {
                return (string) $knownCategory;
            }
        }

        return $trimmed;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeText(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(FinancialTransaction $transaction, ?FinancialAccount $account): array
    {
        return [
            'id' => $transaction->id,
            'accountId' => $transaction->account_id,
            'accountName' => $account?->name,
            'accountLast4' => $account?->number_last4,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'occurredAt' => $transaction->occurred_at?->toIso8601String(),
            'description' => $transaction->description,
            'merchant' => $transaction->merchant,
            'category' => $transaction->category,
            'data' => $transaction->data,
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
            'targetAmount' => (float) $budget->target_amount,
            'currentAmount' => (float) $budget->current_amount,
            'remainingAmount' => max(0, (float) $budget->target_amount - (float) $budget->current_amount),
            'currency' => $budget->currency,
            'icon' => $budget->icon,
            'category' => $budget->category,
            'data' => $budget->data,
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
            'last4' => $account->number_last4,
            'balance' => (float) $account->balance,
            'currency' => $account->currency,
            'creditData' => $account->credit_data,
        ];
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return strtoupper($currency) === 'BRL'
            ? 'R$ '.number_format($amount, 2, ',', '.')
            : strtoupper($currency).' '.number_format($amount, 2, '.', ',');
    }
}
