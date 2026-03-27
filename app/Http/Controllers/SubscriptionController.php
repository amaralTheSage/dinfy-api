<?php

namespace App\Http\Controllers;

use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function plans()
    {
        return response()->json([
            'plans' => $this->subscriptions->plans(),
        ]);
    }

    public function current(Request $request)
    {
        $subscription = $this->subscriptions->currentForUser(
            $request->user(),
            $request->boolean('sync'),
        );

        return response()->json([
            'subscription' => $subscription ? $this->serializeSubscription($subscription) : null,
        ]);
    }

    public function checkout(Request $request)
    {
        Log::info('1. Entrou em SubscriptionController@checkout');

        try {
            $validated = $request->validate([
                'plan' => ['required', 'string', Rule::in(array_keys(config('subscriptions.plans', [])))],
                'payer_email' => ['nullable', 'email:rfc'],
                'card_token_id' => ['nullable', 'string'],
                'device_session_id' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('2. Falha na validacao em SubscriptionController@checkout', [
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        Log::info('2. Validacao concluida em SubscriptionController@checkout', [
            'plan' => $validated['plan'],
        ]);

        $subscription = $this->subscriptions->createCheckout(
            $request->user(),
            (string) $validated['plan'],
            isset($validated['payer_email']) ? (string) $validated['payer_email'] : null,
            isset($validated['card_token_id']) ? (string) $validated['card_token_id'] : null,
            isset($validated['device_session_id']) ? (string) $validated['device_session_id'] : null,
        );

        Log::info('9. SubscriptionController@checkout finalizado com sucesso', [
            'subscription_id' => $subscription->id,
            'plan' => $validated['plan'],
            'status' => $subscription->status,
        ]);

        return response()->json([
            'subscription' => $this->serializeSubscription($subscription),
        ], 201);
    }

    public function cancel(Request $request)
    {
        Log::info('1. Entrou em SubscriptionController@cancel');

        $subscription = $this->subscriptions->cancelCurrent($request->user());

        Log::info('9. SubscriptionController@cancel finalizado com sucesso', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        return response()->json([
            'subscription' => $this->serializeSubscription($subscription),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSubscription(UserSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'plan' => $subscription->plan_code,
            'plan_name' => $subscription->plan_name,
            'status' => $subscription->status,
            'reason' => $subscription->reason,
            'amount' => (float) $subscription->transaction_amount,
            'currency_id' => $subscription->currency_id,
            'frequency' => $subscription->frequency,
            'frequency_type' => $subscription->frequency_type,
            'checkout_url' => $subscription->checkout_url,
            'sandbox_checkout_url' => $subscription->sandbox_checkout_url,
            'mercado_pago_preapproval_id' => $subscription->mercado_pago_preapproval_id,
            'mercado_pago_payment_id' => $subscription->mercado_pago_payment_id,
            'external_reference' => $subscription->external_reference,
            'latest_payment_status' => $subscription->latest_payment_status,
            'latest_payment_status_detail' => $subscription->latest_payment_status_detail,
            'started_at' => $subscription->started_at?->toIso8601String(),
            'next_payment_at' => $subscription->next_payment_at?->toIso8601String(),
            'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
            'updated_at' => $subscription->updated_at?->toIso8601String(),
        ];
    }
}
