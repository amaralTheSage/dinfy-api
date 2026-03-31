<?php

use App\Models\UserSubscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:expire', function () {
    $now = now();

    $subscriptions = UserSubscription::query()
        ->whereIn('status', ['authorized', 'active'])
        ->whereNotNull('next_payment_at')
        ->where('next_payment_at', '<=', $now)
        ->get();

    $this->info(sprintf('Found %d overdue subscriptions.', $subscriptions->count()));

    foreach ($subscriptions as $subscription) {
        $subscription->forceFill([
            'status' => 'canceled',
            'canceled_at' => $subscription->canceled_at ?? $now,
            'next_payment_at' => null,
            'latest_payment_status' => 'expired',
            'latest_payment_status_detail' => 'payment_overdue',
        ])->save();

        $user = $subscription->user;
        if ($user) {
            $current = $user->subscriptions()
                ->orderByRaw("case when status in ('authorized','active') then 0 when status = 'pending' then 1 when status = 'paused' then 2 when status = 'canceled' then 3 else 4 end")
                ->latest('created_at')
                ->first();

            if ($current) {
                $user->forceFill([
                    'subscription_provider' => $current->provider,
                    'subscription_plan' => $current->plan_code,
                    'subscription_status' => $current->status,
                    'subscription_reference' => $current->mercado_pago_preapproval_id ?: $current->external_reference,
                    'subscription_started_at' => $current->started_at,
                    'subscription_renews_at' => $current->next_payment_at,
                    'subscription_canceled_at' => $current->canceled_at,
                ])->save();
            } else {
                $user->forceFill([
                    'subscription_provider' => null,
                    'subscription_plan' => null,
                    'subscription_status' => null,
                    'subscription_reference' => null,
                    'subscription_started_at' => null,
                    'subscription_renews_at' => null,
                    'subscription_canceled_at' => null,
                ])->save();
            }
        }
    }

    $this->info('Overdue subscriptions expired and user summaries synchronized.');
})->purpose('Expire overdue subscriptions and reconcile user subscription summary');
