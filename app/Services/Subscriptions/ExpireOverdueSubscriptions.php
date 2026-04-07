<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\UserSubscription;
use Illuminate\Support\Carbon;

class ExpireOverdueSubscriptions
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function handle(?Carbon $now = null): int
    {
        $resolvedNow = $now?->copy() ?? now();

        $subscriptions = UserSubscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('next_payment_at')
            ->where('next_payment_at', '<=', $resolvedNow)
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->subscriptions->expireDueSubscription($subscription, $resolvedNow);
        }

        return $subscriptions->count();
    }
}
