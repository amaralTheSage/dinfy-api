<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    public function index(Request $request)
    {
        return FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(50);
    }

    public function show(Request $request, string $account)
    {
        $model = FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $account)
            ->firstOrFail();

        return response()->json($model);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'type' => ['required', 'string', 'max:50'],
            'subtype' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'marketingName' => ['nullable', 'string', 'max:255'],
            'taxNumber' => ['nullable', 'string', 'max:30'],
            'owner' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:30'],
            'balance' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'creditData' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ]);

        $id = $validated['id'] ?? null;

        if ($id) {
            $existing = FinancialAccount::query()
                ->where('user_id', $user->id)
                ->where('id', $id)
                ->first();
        } else {
            $existing = null;
        }

        $model = $existing ?? new FinancialAccount;

        if ($id && ! $existing) {
            $model->id = $id;
        }

        $model->user_id = $user->id;
        $model->type = $validated['type'];
        $model->subtype = $validated['subtype'] ?? null;
        $model->name = $validated['name'];
        $model->marketing_name = $validated['marketingName'] ?? null;
        $model->tax_number = $validated['taxNumber'] ?? null;
        $model->owner = $validated['owner'] ?? null;
        $model->number_last4 = isset($validated['number'])
            ? substr(preg_replace('/\D+/', '', (string) $validated['number']), -4) ?: null
            : null;
        $model->balance = $validated['balance'] ?? 0;
        $model->currency = $validated['currency'] ?? 'BRL';
        $model->credit_data = $validated['creditData'] ?? null;
        $model->data = $validated['data'] ?? null;

        $model->save();

        return response()->json($model, $existing ? 200 : 201);
    }

    public function update(Request $request, string $account)
    {
        $user = $request->user();

        $model = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->where('id', $account)
            ->firstOrFail();

        $validated = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'subtype' => ['sometimes', 'nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'marketingName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'taxNumber' => ['sometimes', 'nullable', 'string', 'max:30'],
            'owner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'balance' => ['sometimes', 'nullable', 'numeric'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'creditData' => ['sometimes', 'nullable', 'array'],
            'data' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('type', $validated)) {
            $model->type = $validated['type'];
        }
        if (array_key_exists('subtype', $validated)) {
            $model->subtype = $validated['subtype'];
        }
        if (array_key_exists('name', $validated)) {
            $model->name = $validated['name'];
        }
        if (array_key_exists('marketingName', $validated)) {
            $model->marketing_name = $validated['marketingName'];
        }
        if (array_key_exists('taxNumber', $validated)) {
            $model->tax_number = $validated['taxNumber'];
        }
        if (array_key_exists('owner', $validated)) {
            $model->owner = $validated['owner'];
        }
        if (array_key_exists('number', $validated)) {
            $model->number_last4 = $validated['number']
                ? (substr(preg_replace('/\D+/', '', (string) $validated['number']), -4) ?: null)
                : null;
        }
        if (array_key_exists('balance', $validated)) {
            $model->balance = $validated['balance'] ?? 0;
        }
        if (array_key_exists('currency', $validated)) {
            $model->currency = $validated['currency'] ?? 'BRL';
        }
        if (array_key_exists('creditData', $validated)) {
            $model->credit_data = $validated['creditData'];
        }
        if (array_key_exists('data', $validated)) {
            $model->data = $validated['data'];
        }

        $model->save();

        return response()->json($model);
    }

    public function destroy(Request $request, string $account)
    {
        $model = FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $account)
            ->firstOrFail();

        $model->delete();

        return response()->json(['ok' => true]);
    }
}
