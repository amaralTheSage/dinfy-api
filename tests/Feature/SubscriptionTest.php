<?php

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.mercadopago.base_url', 'https://api.mercadopago.com');
    config()->set('services.mercadopago.access_token', 'test-token');
    config()->set('services.mercadopago.webhook_secret', '');
    config()->set('subscriptions.back_url', 'https://dinfy.app/assinatura');
    config()->set('subscriptions.notification_url', 'https://api.dinfy.app/api/mercado-pago/webhook');
});

it('lists the available subscription plans', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/subscriptions/plans');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'plans')
        ->assertJsonPath('plans.0.code', 'monthly')
        ->assertJsonPath('plans.0.amount', 0.01)
        ->assertJsonPath('plans.1.code', 'yearly')
        ->assertJsonPath('plans.1.amount', 0.01)
        ->assertJsonPath('plans.1.monthly_equivalent', 0.01);
});

it('creates a monthly subscription checkout and stores the local summary', function () {
    Http::fake([
        'https://api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapp_123',
            'status' => 'pending',
            'init_point' => 'https://mp.example/checkout',
            'sandbox_init_point' => 'https://sandbox.mp.example/checkout',
            'reason' => 'Dinfy Premium - Mensal',
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => 0.01,
                'currency_id' => 'BRL',
            ],
            'date_created' => '2026-03-24T12:00:00.000Z',
        ], 201),
    ]);

    $user = User::factory()->create([
        'email' => 'gabriel@example.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/checkout', [
        'plan' => 'monthly',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('subscription.plan', 'monthly')
        ->assertJsonPath('subscription.status', 'pending')
        ->assertJsonPath('subscription.checkout_url', 'https://mp.example/checkout')
        ->assertJsonPath('subscription.mercado_pago_preapproval_id', 'preapp_123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.mercadopago.com/preapproval'
            && $request['payer_email'] === 'gabriel@example.com'
            && $request['status'] === 'pending'
            && $request['back_url'] === 'https://dinfy.app/assinatura'
            && $request['notification_url'] === 'https://api.dinfy.app/api/mercado-pago/webhook'
            && $request['auto_recurring']['frequency'] === 1
            && $request['auto_recurring']['frequency_type'] === 'months'
            && (float) $request['auto_recurring']['transaction_amount'] === 0.01;
    });

    $this->assertDatabaseHas('user_subscriptions', [
        'user_id' => $user->id,
        'plan_code' => 'monthly',
        'status' => 'pending',
        'mercado_pago_preapproval_id' => 'preapp_123',
    ]);

    $user->refresh();

    expect($user->subscription_provider)->toBe('mercado_pago');
    expect($user->subscription_plan)->toBe('monthly');
    expect($user->subscription_status)->toBe('pending');
    expect($user->subscription_reference)->toBe('preapp_123');
});

it('creates a yearly subscription checkout and stores the local summary', function () {
    Http::fake([
        'https://api.mercadopago.com/preapproval' => Http::response([
            'id' => 'preapp_yearly_123',
            'status' => 'pending',
            'init_point' => 'https://mp.example/yearly-checkout',
            'sandbox_init_point' => 'https://sandbox.mp.example/yearly-checkout',
            'reason' => 'Dinfy Premium - Anual',
            'auto_recurring' => [
                'frequency' => 12,
                'frequency_type' => 'months',
                'transaction_amount' => 0.01,
                'currency_id' => 'BRL',
            ],
            'date_created' => '2026-03-24T12:00:00.000Z',
        ], 201),
    ]);

    $user = User::factory()->create([
        'email' => 'gabriel@example.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/checkout', [
        'plan' => 'yearly',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('subscription.plan', 'yearly')
        ->assertJsonPath('subscription.status', 'pending')
        ->assertJsonPath('subscription.checkout_url', 'https://mp.example/yearly-checkout')
        ->assertJsonPath('subscription.mercado_pago_preapproval_id', 'preapp_yearly_123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.mercadopago.com/preapproval'
            && $request['payer_email'] === 'gabriel@example.com'
            && $request['status'] === 'pending'
            && $request['back_url'] === 'https://dinfy.app/assinatura'
            && $request['notification_url'] === 'https://api.dinfy.app/api/mercado-pago/webhook'
            && $request['auto_recurring']['frequency'] === 12
            && $request['auto_recurring']['frequency_type'] === 'months'
            && (float) $request['auto_recurring']['transaction_amount'] === 0.01;
    });

    $this->assertDatabaseHas('user_subscriptions', [
        'user_id' => $user->id,
        'plan_code' => 'yearly',
        'status' => 'pending',
        'checkout_url' => 'https://mp.example/yearly-checkout',
        'mercado_pago_preapproval_id' => 'preapp_yearly_123',
    ]);

    $user->refresh();

    expect($user->subscription_provider)->toBe('mercado_pago');
    expect($user->subscription_plan)->toBe('yearly');
    expect($user->subscription_status)->toBe('pending');
    expect($user->subscription_reference)->not()->toBeEmpty();
});

it('prevents creating a second subscription while one is still open', function () {
    Http::fake();

    $user = User::factory()->create();

    UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'pending',
        'external_reference' => 'dinfy:user:' . $user->id . ':plan:monthly:existing',
        'mercado_pago_preapproval_id' => 'preapp_existing',
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/checkout', [
        'plan' => 'yearly',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan');

    Http::assertNothingSent();
});

it('cancels a pending monthly preapproval locally without calling mercado pago', function () {
    Http::fake();

    $user = User::factory()->create();

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'pending',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-monthly-123',
        'mercado_pago_preapproval_id' => 'preapp_pending_123',
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'checkout_url' => 'https://mp.example/subscription-checkout',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/current/cancel');

    $response
        ->assertOk()
        ->assertJsonPath('subscription.status', 'canceled')
        ->assertJsonPath('subscription.checkout_url', null)
        ->assertJsonPath('subscription.mercado_pago_preapproval_id', 'preapp_pending_123');

    Http::assertNothingSent();

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->checkout_url)->toBeNull();
    expect($subscription->mercado_pago_preapproval_id)->toBe('preapp_pending_123');
    expect($user->subscription_status)->toBe('canceled');
});

it('cancels a pending yearly checkout payment through mercado pago when the payment already exists', function () {
    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_pending_123' => Http::response([
            'id' => 'pay_pending_123',
            'external_reference' => 'dinfy-u-1-p-yearly-123',
            'status' => 'canceled',
            'status_detail' => 'by_payer',
            'date_last_updated' => '2026-03-24T13:00:00.000Z',
        ], 200),
    ]);

    $user = User::factory()->create();

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'yearly',
        'plan_name' => 'Anual',
        'status' => 'pending',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-yearly-123',
        'mercado_pago_payment_id' => 'pay_pending_123',
        'reason' => 'Dinfy Premium - Anual',
        'transaction_amount' => 97.00,
        'currency_id' => 'BRL',
        'frequency' => 12,
        'frequency_type' => 'months',
        'latest_payment_status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/current/cancel');

    $response
        ->assertOk()
        ->assertJsonPath('subscription.status', 'canceled')
        ->assertJsonPath('subscription.latest_payment_status', 'canceled')
        ->assertJsonPath('subscription.mercado_pago_payment_id', 'pay_pending_123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.mercadopago.com/v1/payments/pay_pending_123'
            && $request->method() === 'PUT'
            && $request['status'] === 'canceled';
    });

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->latest_payment_status)->toBe('canceled');
    expect($user->subscription_status)->toBe('canceled');
});

it('cancels a local yearly checkout before a payment is created', function () {
    Http::fake();

    $user = User::factory()->create();

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'yearly',
        'plan_name' => 'Anual',
        'status' => 'pending',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-yearly-123',
        'reason' => 'Dinfy Premium - Anual',
        'transaction_amount' => 97.00,
        'currency_id' => 'BRL',
        'frequency' => 12,
        'frequency_type' => 'months',
        'checkout_url' => 'https://mp.example/pix-checkout',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/current/cancel');

    $response
        ->assertOk()
        ->assertJsonPath('subscription.status', 'canceled')
        ->assertJsonPath('subscription.checkout_url', null);

    Http::assertNothingSent();

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->checkout_url)->toBeNull();
    expect($user->subscription_status)->toBe('canceled');
});

it('ignores webhook updates for a locally canceled pending preapproval checkout', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy-u-' . $user->id . '-p-monthly-abc';

    Http::fake([
        'https://api.mercadopago.com/preapproval/search*' => Http::response([
            'results' => [[
                'id' => 'preapp_123',
                'external_reference' => $externalReference,
                'status' => 'authorized',
                'reason' => 'Dinfy Premium - Mensal',
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => 19.90,
                    'currency_id' => 'BRL',
                ],
                'date_created' => '2026-03-24T12:00:00.000Z',
                'next_payment_date' => '2026-04-24T12:00:00.000Z',
            ]],
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'canceled',
        'external_reference' => $externalReference,
        'mercado_pago_preapproval_id' => 'preapp_123',
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'canceled_at' => now(),
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=preapp_123', [
        'type' => 'subscription_preapproval',
        'data' => [
            'id' => 'preapp_123',
        ],
    ]);

    $response->assertOk()->assertJsonPath('ok', true);

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->started_at)->toBeNull();
});

it('updates the subscription status from a mercado pago webhook', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy:user:' . $user->id . ':plan:monthly:abc';

    Http::fake([
        'https://api.mercadopago.com/preapproval/search*' => Http::response([
            'results' => [[
                'id' => 'preapp_123',
                'external_reference' => $externalReference,
                'status' => 'authorized',
                'reason' => 'Dinfy Premium - Mensal',
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => 19.90,
                    'currency_id' => 'BRL',
                ],
                'date_created' => '2026-03-24T12:00:00.000Z',
                'next_payment_date' => '2026-04-24T12:00:00.000Z',
            ]],
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'pending',
        'external_reference' => $externalReference,
        'mercado_pago_preapproval_id' => 'preapp_123',
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=preapp_123', [
        'type' => 'subscription_preapproval',
        'data' => [
            'id' => 'preapp_123',
        ],
    ]);

    $response->assertOk()->assertJsonPath('ok', true);

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('authorized');
    expect($subscription->next_payment_at?->toIso8601String())->toStartWith('2026-04-24T12:00:00');
    expect($user->subscription_status)->toBe('authorized');
    expect($user->subscription_reference)->toBe('preapp_123');
});

it('rejects webhook requests with an invalid signature when a secret is configured', function () {
    config()->set('services.mercadopago.webhook_secret', 'super-secret');

    Http::fake();

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=preapp_123', [
        'type' => 'subscription_preapproval',
        'data' => [
            'id' => 'preapp_123',
        ],
    ], [
        'x-signature' => 'ts=1704908010,v1=invalid',
        'x-request-id' => 'request-123',
    ]);

    $response->assertUnauthorized();

    Http::assertNothingSent();
});

it('accepts a valid webhook signature even when data.id exists only in the body', function () {
    config()->set('services.mercadopago.webhook_secret', 'super-secret');

    $ts = '1704908010';
    $requestId = 'request-123';
    $signature = hash_hmac('sha256', "request-id:{$requestId};ts:{$ts};", 'super-secret');

    Http::fake();

    $response = $this->postJson('/api/mercado-pago/webhook', [
        'type' => 'unknown-topic',
        'data' => [
            'id' => 'preapp_123',
        ],
    ], [
        'x-signature' => "ts={$ts},v1={$signature}",
        'x-request-id' => $requestId,
    ]);

    $response->assertOk()->assertJsonPath('ok', true);

    Http::assertNothingSent();
});

it('stores the authorized payment id separately from the real payment id', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy:user:' . $user->id . ':plan:monthly:abc';

    Http::fake([
        'https://api.mercadopago.com/authorized_payments/authpay_123' => Http::response([
            'id' => 'authpay_123',
            'preapproval_id' => 'preapp_123',
            'payment_id' => 'pay_987',
            'status' => 'processed',
            'date_created' => '2026-03-24T12:00:00.000Z',
        ], 200),
        'https://api.mercadopago.com/preapproval/search*' => Http::response([
            'results' => [[
                'id' => 'preapp_123',
                'external_reference' => $externalReference,
                'status' => 'authorized',
                'reason' => 'Dinfy Premium - Mensal',
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => 19.90,
                    'currency_id' => 'BRL',
                ],
                'date_created' => '2026-03-24T12:00:00.000Z',
            ]],
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'pending',
        'external_reference' => $externalReference,
        'mercado_pago_preapproval_id' => 'preapp_123',
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=authpay_123', [
        'type' => 'subscription_authorized_payment',
        'data' => [
            'id' => 'authpay_123',
        ],
    ]);

    $response->assertOk();

    $subscription->refresh();

    expect($subscription->mercado_pago_authorized_payment_id)->toBe('authpay_123');
    expect($subscription->mercado_pago_payment_id)->toBe('pay_987');
    expect($subscription->latest_payment_status)->toBe('processed');
});

it('uses the payment timestamps instead of the local current time when syncing payments', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy:user:' . $user->id . ':plan:monthly:abc';

    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_987' => Http::response([
            'id' => 'pay_987',
            'external_reference' => $externalReference,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'date_approved' => '2026-03-24T12:34:56.000Z',
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'monthly',
        'plan_name' => 'Mensal',
        'status' => 'pending',
        'external_reference' => $externalReference,
        'reason' => 'Dinfy Premium - Mensal',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=pay_987', [
        'type' => 'payment',
        'data' => [
            'id' => 'pay_987',
        ],
    ]);

    $response->assertOk();

    $subscription->refresh();

    expect($subscription->mercado_pago_payment_id)->toBe('pay_987');
    expect($subscription->started_at?->toIso8601String())->toStartWith('2026-03-24T12:34:56');
});

it('activates the yearly checkout plan for 12 months when the payment is approved', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy-u-' . $user->id . '-p-yearly-abc';

    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_yearly_123' => Http::response([
            'id' => 'pay_yearly_123',
            'external_reference' => $externalReference,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'date_approved' => '2026-03-24T12:34:56.000Z',
            'point_of_interaction' => [
                'transaction_data' => [
                    'ticket_url' => 'https://mp.example/pix-ticket',
                ],
            ],
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'mercado_pago',
        'plan_code' => 'yearly',
        'plan_name' => 'Anual',
        'status' => 'pending',
        'external_reference' => $externalReference,
        'reason' => 'Dinfy Premium - Anual',
        'transaction_amount' => 97.00,
        'currency_id' => 'BRL',
        'frequency' => 12,
        'frequency_type' => 'months',
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=pay_yearly_123', [
        'type' => 'payment',
        'data' => [
            'id' => 'pay_yearly_123',
        ],
    ]);

    $response->assertOk();

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('active');
    expect($subscription->mercado_pago_payment_id)->toBe('pay_yearly_123');
    expect($subscription->checkout_url)->toBe('https://mp.example/pix-ticket');
    expect($subscription->started_at?->toIso8601String())->toStartWith('2026-03-24T12:34:56');
    expect($subscription->next_payment_at?->toIso8601String())->toStartWith('2027-03-24T12:34:56');
    expect($user->subscription_status)->toBe('active');
    expect($user->subscription_plan)->toBe('yearly');
});
