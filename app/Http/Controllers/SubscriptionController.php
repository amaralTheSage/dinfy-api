<?php

namespace App\Http\Controllers;

use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {
    }

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
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys(config('subscriptions.plans', [])))],
            'payer_email' => ['nullable', 'email:rfc'],
        ]);

        $subscription = $this->subscriptions->createCheckout(
            $request->user(),
            (string) $validated['plan'],
            isset($validated['payer_email']) ? (string) $validated['payer_email'] : null,
        );

        return response()->json([
            'subscription' => $this->serializeSubscription($subscription),
        ], 201);
    }

    public function cancel(Request $request)
    {
        $subscription = $this->subscriptions->cancelCurrent($request->user());

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
