<?php

use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.mercadopago.base_url', 'https://api.mercadopago.com');
    config()->set('services.mercadopago.access_token', 'test-token');
    config()->set('services.mercadopago.public_key', 'test-public-key');
    config()->set('services.mercadopago.webhook_secret', '');
    config()->set('subscriptions.back_url', 'https://dinfy.app/assinatura');
    config()->set('subscriptions.notification_url', 'https://api.dinfy.app/api/mercado-pago/webhook');
    config()->set('subscriptions.plans', [
        'monthly' => [
            'name' => 'Mensal',
            'reason' => 'Dinfy - Mensal',
            'amount' => 19.90,
            'currency_id' => 'BRL',
            'frequency' => 1,
            'frequency_type' => 'months',
            'monthly_equivalent' => 19.90,
            'checkout_mode' => 'pix',
        ],
        'yearly' => [
            'name' => 'Anual',
            'reason' => 'Dinfy - Anual',
            'amount' => 97.00,
            'currency_id' => 'BRL',
            'frequency' => 12,
            'frequency_type' => 'months',
            'monthly_equivalent' => round(97.00 / 12, 2),
            'checkout_mode' => 'pix',
        ],
    ]);
});

it('lists the available subscription plans', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/subscriptions/plans');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'plans')
        ->assertJsonPath('plans.0.code', 'monthly')
        ->assertJsonPath('plans.1.code', 'yearly')
        ->assertJsonPath('plans.0.frequency_type', 'months')
        ->assertJsonPath('plans.1.frequency', 12);
});

it('returns a null current subscription when the user has no subscription yet', function () {
    Http::fake();

    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/subscriptions/current?sync=1');

    $response
        ->assertOk()
        ->assertJsonPath('subscription', null);

    Http::assertNothingSent();
});

it('creates a monthly hosted pix checkout and stores the first invoice', function () {
    Http::fake([
        'https://api.mercadopago.com/v1/payments' => Http::response([
            'id' => 'pay_pix_123',
            'status' => 'pending',
            'status_detail' => 'pending_waiting_payment',
            'transaction_amount' => 19.90,
            'currency_id' => 'BRL',
            'date_created' => '2026-03-31T12:00:00.000Z',
            'date_of_expiration' => '2026-04-03T00:00:00.000Z',
            'point_of_interaction' => [
                'transaction_data' => [
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_pix_123',
                    'qr_code' => 'QR123',
                    'qr_code_base64' => 'BASE64QR123',
                    'expiration_date' => '2026-04-03T00:00:00.000Z',
                ],
            ],
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
        ->assertJsonPath('subscription.provider', 'pix')
        ->assertJsonPath('subscription.status', 'pending')
        ->assertJsonPath(
            'subscription.checkout_url',
            'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_pix_123',
        )
        ->assertJsonPath('subscription.latest_invoice.provider_payment_id', 'pay_pix_123')
        ->assertJsonPath('subscription.latest_invoice.qr_code', 'QR123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $request['payment_method_id'] === 'pix'
            && $request['transaction_amount'] === 19.90
            && data_get($request->data(), 'payer.email') === 'gabriel@example.com'
            && $request['notification_url'] === 'https://api.dinfy.app/api/mercado-pago/webhook';
    });

    $this->assertDatabaseHas('user_subscriptions', [
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'pending',
        'mercado_pago_payment_id' => 'pay_pix_123',
        'checkout_url' => 'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_pix_123',
    ]);

    $this->assertDatabaseHas('subscription_invoices', [
        'provider_payment_id' => 'pay_pix_123',
        'user_subscription_id' => $response->json('subscription.id'),
        'status' => 'pending',
    ]);
});

it('rejects card as a checkout payment method', function () {
    Http::fake();

    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/checkout', [
        'plan' => 'monthly',
        'payment_method' => 'card',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');

    Http::assertNothingSent();
});

it('prevents creating a second subscription while one is still open', function () {
    Http::fake();

    $user = User::factory()->create();

    UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'pending',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-monthly-existing',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'mercado_pago_payment_id' => 'pay_existing_123',
        'latest_payment_status' => 'pending',
        'checkout_url' => 'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_existing_123',
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

it('cancels a pending pix payment through mercado pago', function () {
    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_pending_123' => Http::response([
            'id' => 'pay_pending_123',
            'external_reference' => 'dinfy-u-1-p-yearly-123',
            'status' => 'canceled',
            'status_detail' => 'by_payer',
            'date_last_updated' => '2026-03-31T14:00:00.000Z',
        ], 200),
    ]);

    $user = User::factory()->create();

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'yearly',
        'status' => 'pending',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-yearly-123',
        'mercado_pago_payment_id' => 'pay_pending_123',
        'transaction_amount' => 97.00,
        'currency_id' => 'BRL',
        'frequency' => 12,
        'frequency_type' => 'months',
        'latest_payment_status' => 'pending',
        'checkout_url' => 'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_pending_123',
    ]);

    SubscriptionInvoice::query()->create([
        'user_subscription_id' => $subscription->id,
        'provider' => 'mercado_pago',
        'provider_payment_id' => 'pay_pending_123',
        'external_reference' => $subscription->external_reference,
        'transaction_amount' => 97.00,
        'currency_id' => 'BRL',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/current/cancel');

    $response
        ->assertOk()
        ->assertJsonPath('subscription.status', 'canceled')
        ->assertJsonPath('subscription.latest_payment_status', 'canceled')
        ->assertJsonPath('subscription.checkout_url', null);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://api.mercadopago.com/v1/payments/pay_pending_123'
            && $request->method() === 'PUT'
            && $request['status'] === 'canceled';
    });

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->latest_payment_status)->toBe('canceled');
    expect($subscription->checkout_url)->toBeNull();
    expect($user->subscription_status)->toBe('canceled');
});

it('cancels an active local pix subscription without calling mercado pago', function () {
    Http::fake();

    $user = User::factory()->create();

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'active',
        'external_reference' => 'dinfy-u-' . $user->id . '-p-monthly-active',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'started_at' => Carbon::parse('2026-03-01T12:00:00.000Z'),
        'next_payment_at' => Carbon::parse('2026-04-01T12:00:00.000Z'),
        'latest_payment_status' => 'approved',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/subscriptions/current/cancel');

    $response
        ->assertOk()
        ->assertJsonPath('subscription.status', 'canceled')
        ->assertJsonPath('subscription.checkout_url', null)
        ->assertJsonPath('subscription.next_payment_at', null);

    Http::assertNothingSent();

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('canceled');
    expect($subscription->next_payment_at)->toBeNull();
    expect($user->subscription_status)->toBe('canceled');
});

it('syncs approved payments using mercado pago timestamps and preserves invoice history', function () {
    $user = User::factory()->create();
    $externalReference = 'dinfy-u-' . $user->id . '-p-monthly-renewal';

    Http::fake([
        'https://api.mercadopago.com/v1/payments/pay_renewal_456' => Http::response([
            'id' => 'pay_renewal_456',
            'external_reference' => $externalReference,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 19.90,
            'currency_id' => 'BRL',
            'date_approved' => '2026-04-01T15:00:00.000Z',
            'date_last_updated' => '2026-04-01T15:01:00.000Z',
            'point_of_interaction' => [
                'transaction_data' => [
                    'ticket_url' => 'https://www.mercadopago.com.br/payments/checkout-v1?payment_id=pay_renewal_456',
                ],
            ],
        ], 200),
    ]);

    $subscription = UserSubscription::query()->create([
        'user_id' => $user->id,
        'provider' => 'pix',
        'plan_code' => 'monthly',
        'status' => 'active',
        'external_reference' => $externalReference,
        'mercado_pago_payment_id' => 'pay_initial_123',
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'frequency' => 1,
        'frequency_type' => 'months',
        'started_at' => Carbon::parse('2026-03-01T12:00:00.000Z'),
        'next_payment_at' => Carbon::parse('2026-04-01T12:00:00.000Z'),
        'latest_payment_status' => 'approved',
    ]);

    SubscriptionInvoice::query()->create([
        'user_subscription_id' => $subscription->id,
        'provider' => 'mercado_pago',
        'provider_payment_id' => 'pay_initial_123',
        'external_reference' => $externalReference,
        'transaction_amount' => 19.90,
        'currency_id' => 'BRL',
        'status' => 'approved',
        'paid_at' => Carbon::parse('2026-03-01T12:00:00.000Z'),
    ]);

    $response = $this->postJson('/api/mercado-pago/webhook?data.id=pay_renewal_456', [
        'type' => 'payment',
        'data' => [
            'id' => 'pay_renewal_456',
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('ok', true);

    $subscription->refresh();
    $user->refresh();

    expect($subscription->status)->toBe('active');
    expect($subscription->mercado_pago_payment_id)->toBe('pay_renewal_456');
    expect($subscription->started_at?->toIso8601String())->toStartWith('2026-04-01T15:00:00');
    expect($subscription->next_payment_at?->toIso8601String())->toStartWith('2026-05-01T15:00:00');
    expect($subscription->checkout_url)->toBeNull();
    expect($user->subscription_status)->toBe('active');

    expect(
        SubscriptionInvoice::query()
            ->where('user_subscription_id', $subscription->id)
            ->count()
    )->toBe(2);

    $renewalInvoice = SubscriptionInvoice::query()
        ->where('provider_payment_id', 'pay_renewal_456')
        ->first();

    expect($renewalInvoice)->not()->toBeNull();
    expect($renewalInvoice?->paid_at?->toIso8601String())->toStartWith('2026-04-01T15:00:00');
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

    $response
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertNothingSent();
});
