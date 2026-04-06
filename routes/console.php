<?php

use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionManager;
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
            app(SubscriptionManager::class)->syncUserSummary($user);
        }
    }

    $this->info('Overdue subscriptions expired and user summaries synchronized.');
})->purpose('Expire overdue subscriptions and reconcile user subscription summary');
