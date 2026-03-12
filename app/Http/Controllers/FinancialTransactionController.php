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

        return FinancialTransaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('occurred_at')
            ->paginate($perPage);
    }

    public function show(Request $request, string $transaction)
    {
        $model = FinancialTransaction::query()
            ->where('user_id', $request->user()->id)
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

        $account = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->where('id', $validated['accountId'])
            ->firstOrFail();

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

        return response()->json($model, 201);
    }
}
