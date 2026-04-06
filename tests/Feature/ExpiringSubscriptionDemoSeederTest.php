<?php

use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Database\Seeders\ExpiringSubscriptionDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('seeds the demo user with an active subscription that renews in one minute', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00'));

    $this->seed(ExpiringSubscriptionDemoSeeder::class);

    $user = User::query()->where('email', 'p@p.com')->first();

    expect($user)->not()->toBeNull();
    expect(Hash::check('p', (string) $user?->password))->toBeTrue();
    expect($user?->subscription_status)->toBe('active');

    $subscription = UserSubscription::query()
        ->where('user_id', $user->id)
        ->first();

    expect($subscription)->not()->toBeNull();
    expect($subscription?->status)->toBe('active');
    expect($subscription?->plan_code)->toBe('monthly');
    expect($subscription?->latest_payment_status)->toBe('approved');
    expect($subscription?->next_payment_at?->toIso8601String())
        ->toStartWith(Carbon::now()->addMinute()->format('Y-m-d\TH:i:s'));

    $invoice = SubscriptionInvoice::query()
        ->where('user_subscription_id', $subscription->id)
        ->latest('id')
        ->first();

    expect($invoice)->not()->toBeNull();
    expect($invoice?->status)->toBe('approved');
    expect($invoice?->provider_payment_id)->toBe($subscription?->mercado_pago_payment_id);
});
