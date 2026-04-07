<?php

use App\Jobs\ExpireOverdueSubscriptionsJob;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('expires overdue subscriptions when the job runs', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-06 12:00:00'));

    $user = User::factory()->create([
        'email' => 'scheduled-expire@example.com',
    ]);

    $overdueSubscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'active',
        'external_reference' => 'dinfy-u-'.$user->id.'-p-monthly-overdue',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'started_at' => Carbon::now()->subMonth(),
        'next_payment_at' => Carbon::now()->subMinute(),
        'latest_payment_status' => 'approved',
    ]);

    $futureSubscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'active',
        'external_reference' => 'dinfy-u-'.$user->id.'-p-monthly-future',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'started_at' => Carbon::now()->subDays(5),
        'next_payment_at' => Carbon::now()->addDay(),
        'latest_payment_status' => 'approved',
    ]);

    ExpireOverdueSubscriptionsJob::dispatchSync();

    $overdueSubscription->refresh();
    $futureSubscription->refresh();
    $user->refresh();

    expect($overdueSubscription->status->value)->toBe('expired');
    expect($overdueSubscription->latest_payment_status)->toBe('expired');
    expect($overdueSubscription->next_payment_at)->toBeNull();

    expect($futureSubscription->status->value)->toBe('active');
    expect($futureSubscription->next_payment_at)->not()->toBeNull();

    expect($user->subscription_status)->toBe('active');
});
