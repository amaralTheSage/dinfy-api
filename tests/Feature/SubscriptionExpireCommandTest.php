<?php

use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires past due active subscriptions and updates user summary', function () {
    $user = User::factory()->create([
        'email' => 'expire@example.com',
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'active',
        'external_reference' => 'dinfy-u-'.$user->id.'-p-monthly-test',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'started_at' => Carbon::now()->subMonth(),
        'next_payment_at' => Carbon::now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire')
        ->expectsOutput('Found 1 overdue subscriptions.')
        ->expectsOutput('Overdue subscriptions expired and user summaries synchronized.')
        ->assertExitCode(0);

    $subscription->refresh();
    expect($subscription->status->value)->toBe('expired');
    expect($subscription->latest_payment_status)->toBe('expired');

    $user->refresh();
    expect($user->subscription_status)->toBe('expired');
});
