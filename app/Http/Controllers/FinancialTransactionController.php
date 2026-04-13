<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        return $this->transactionsQuery($request)
            ->latest('occurred_at')
            ->paginate($perPage);
    }

    public function show(Request $request, string $transaction)
    {
        $model = $this->transactionsQuery($request)
            ->where('id', $transaction)
            ->firstOrFail();

        return response()->json($model);
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

        $model = new FinancialTransaction();
        if (!empty($validated['id'])) {
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
}
