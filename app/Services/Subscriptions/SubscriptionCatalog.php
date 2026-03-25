<?php

namespace App\Services\Subscriptions;

class SubscriptionCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $plans = config('subscriptions.plans', []);

        if (!is_array($plans)) {
            return [];
        }

        return collect($plans)
            ->map(fn ($plan, $code) => is_array($plan) ? $this->normalize($code, $plan) : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $code): ?array
    {
        $plan = config("subscriptions.plans.{$code}");

        if (!is_array($plan)) {
            return null;
        }

        return $this->normalize($code, $plan);
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function normalize(string $code, array $plan): array
    {
        return [
            'code' => $code,
            'name' => (string) ($plan['name'] ?? strtoupper($code)),
            'reason' => (string) ($plan['reason'] ?? strtoupper($code)),
            'amount' => round((float) ($plan['amount'] ?? 0), 2),
            'currency_id' => (string) ($plan['currency_id'] ?? config('subscriptions.currency', 'BRL')),
            'frequency' => (int) ($plan['frequency'] ?? 1),
            'frequency_type' => (string) ($plan['frequency_type'] ?? 'months'),
            'monthly_equivalent' => round((float) ($plan['monthly_equivalent'] ?? 0), 2),
            'checkout_mode' => (string) ($plan['checkout_mode'] ?? 'subscription_pending'),
        ];
    }
}
