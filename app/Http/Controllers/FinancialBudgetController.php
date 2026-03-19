<?php

namespace App\Http\Controllers;

use App\Models\FinancialBudget;
use Illuminate\Http\Request;

class FinancialBudgetController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 1) {
            $perPage = 50;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        return FinancialBudget::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);
    }

    public function show(Request $request, string $budget)
    {
        $model = FinancialBudget::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $budget)
            ->firstOrFail();

        return response()->json($model);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'targetAmount' => ['required', 'numeric', 'min:0'],
            'currentAmount' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'data' => ['nullable', 'array'],
        ]);

        $model = new FinancialBudget();
        if (!empty($validated['id'])) {
            $model->id = $validated['id'];
        }

        $model->user_id = $request->user()->id;
        $model->name = $validated['name'];
        $model->target_amount = $validated['targetAmount'];
        $model->current_amount = $validated['currentAmount'] ?? 0;
        $model->icon = $validated['icon'] ?? null;
        $model->category = $validated['category'] ?? null;
        $model->currency = $validated['currency'] ?? 'BRL';
        $model->data = $validated['data'] ?? null;

        $model->save();

        return response()->json($model, 201);
    }

    public function update(Request $request, string $budget)
    {
        $model = FinancialBudget::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $budget)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'targetAmount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currentAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'data' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('name', $validated)) {
            $model->name = $validated['name'];
        }
        if (array_key_exists('targetAmount', $validated)) {
            $model->target_amount = $validated['targetAmount'];
        }
        if (array_key_exists('currentAmount', $validated)) {
            $model->current_amount = $validated['currentAmount'] ?? 0;
        }
        if (array_key_exists('icon', $validated)) {
            $model->icon = $validated['icon'];
        }
        if (array_key_exists('category', $validated)) {
            $model->category = $validated['category'];
        }
        if (array_key_exists('currency', $validated)) {
            $model->currency = $validated['currency'] ?? 'BRL';
        }
        if (array_key_exists('data', $validated)) {
            $model->data = $validated['data'];
        }

        $model->save();

        return response()->json($model);
    }

    public function destroy(Request $request, string $budget)
    {
        $model = FinancialBudget::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $budget)
            ->firstOrFail();

        $model->delete();

        return response()->json(['ok' => true]);
    }
}
