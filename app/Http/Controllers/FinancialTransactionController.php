<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 500) {
            $perPage = 500;
        }

        $query = $this->transactionsQuery($request);
        $accountId = trim((string) ($request->query('accountId') ?? $request->query('account_id') ?? ''));

        if ($accountId !== '') {
            $query->where('account_id', $this->resolveAccount($request, $accountId)->id);
        }

        return $query->latest('occurred_at')->paginate($perPage);
    }

    public function show(Request $request, string $transaction)
    {
        $model = $this->transactionsQuery($request)
            ->where('id', $transaction)
            ->firstOrFail();

        return response()->json($model);
    }

    public function summary(Request $request)
    {
        $month = trim((string) $request->query('month', now()->format('Y-m')));

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw ValidationException::withMessages([
                'month' => ['Informe o mes no formato YYYY-MM.'],
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $transactions = FinancialTransaction::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->get(['type', 'amount']);

        $income = 0.0;
        $expenses = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            if (strtoupper((string) $transaction->type) === 'CREDIT') {
                $income += $amount;
            } else {
                $expenses += $amount;
            }
        }

        return response()->json([
            'month' => $start->format('Y-m'),
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'balance' => round($income - $expenses, 2),
            'currency' => 'BRL',
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'accountId' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:30'], // e.g. DEBIT / CREDIT
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'occurredAt' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'array'],
        ]);

        $account = $this->resolveAccount($request, $validated['accountId']);

        $model = new FinancialTransaction;
        if (! empty($validated['id'])) {
            $model->id = $validated['id'];
        }

        $model->user_id = $user->id;
        $model->account_id = $account->id;
        $model->type = $validated['type'];
        $model->amount = $validated['amount'];
        $model->currency = $validated['currency'] ?? 'BRL';
        $model->occurred_at = $validated['occurredAt'];
        $model->description = $validated['description'] ?? null;
        $model->merchant = $validated['merchant'] ?? null;
        $model->category = $validated['category'] ?? null;
        $model->data = $validated['data'] ?? null;

        $model->save();

        return response()->json(
            $model->load('account:id,name,marketing_name,number_last4'),
            201
        );
    }

    public function update(Request $request, string $transaction)
    {
        $model = FinancialTransaction::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $transaction)
            ->firstOrFail();

        $this->ensureTransactionCanBeChanged($model);

        $validated = $request->validate([
            'accountId' => ['sometimes', 'nullable', 'uuid'],
            'type' => ['sometimes', 'required', 'string', 'max:30'],
            'amount' => ['sometimes', 'required', 'numeric'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'occurredAt' => ['sometimes', 'required', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'merchant' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'data' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('accountId', $validated)) {
            $accountId = $validated['accountId'];
            $model->account_id = $accountId !== null
                ? $this->resolveAccount($request, $accountId)?->id
                : null;
        }
        if (array_key_exists('type', $validated)) {
            $model->type = $validated['type'];
        }
        if (array_key_exists('amount', $validated)) {
            $model->amount = $validated['amount'];
        }
        if (array_key_exists('currency', $validated)) {
            $model->currency = $validated['currency'] ?? 'BRL';
        }
        if (array_key_exists('occurredAt', $validated)) {
            $model->occurred_at = $validated['occurredAt'];
        }
        if (array_key_exists('description', $validated)) {
            $model->description = $validated['description'] ?? null;
        }
        if (array_key_exists('merchant', $validated)) {
            $model->merchant = $validated['merchant'] ?? null;
        }
        if (array_key_exists('category', $validated)) {
            $model->category = $validated['category'] ?? null;
        }
        if (array_key_exists('data', $validated)) {
            $model->data = $validated['data'] ?? null;
        }

        $model->save();

        return response()->json(
            $model->load('account:id,name,marketing_name,number_last4')
        );
    }

    public function destroy(Request $request, string $transaction)
    {
        $model = FinancialTransaction::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $transaction)
            ->firstOrFail();

        $this->ensureTransactionCanBeChanged($model);

        $model->delete();

        return response()->json(['ok' => true]);
    }

    private function transactionsQuery(Request $request)
    {
        return FinancialTransaction::query()
            ->with('account:id,name,marketing_name,number_last4')
            ->where('user_id', $request->user()->id);
    }

    private function resolveAccount(Request $request, ?string $accountId): ?FinancialAccount
    {
        if ($accountId === null || trim($accountId) === '') {
            return null;
        }

        return FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $accountId)
            ->firstOrFail();
    }

    private function ensureTransactionCanBeChanged(FinancialTransaction $transaction): void
    {
        $provider = strtolower((string) $transaction->provider);
        $dataProvider = strtolower((string) data_get($transaction->data, 'provider'));

        if ($provider === 'openfinance' || $dataProvider === 'openfinance') {
            throw ValidationException::withMessages([
                'transaction' => ['Transacoes importadas via Open Finance sao somente leitura.'],
            ]);
        }
    }
}
