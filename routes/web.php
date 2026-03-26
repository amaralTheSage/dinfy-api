<?php

use App\Http\Controllers\SubscriptionCheckoutSessionController;
use Illuminate\Support\Facades\Route;

$testCheckoutData = function (): array {
    $publicKey = trim((string) config('services.mercadopago.public_key', ''));

    $preapprovalPlan = [
        'id' => '9436bbe8bf8748d18375917f697dd1db',
        'back_url' => 'https://dinfy.app',
        'collector_id' => 2636680950,
        'application_id' => 6122342385970614,
        'reason' => 'Dinfy Anual',
        'status' => 'active',
        'date_created' => '2026-03-26T11:34:21.997-04:00',
        'last_modified' => '2026-03-26T11:34:21.997-04:00',
        'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_plan_id=9436bbe8bf8748d18375917f697dd1db',
        'auto_recurring' => [
            'frequency' => 12,
            'frequency_type' => 'months',
            'transaction_amount' => 0.5,
            'currency_id' => 'BRL',
        ],
        'payment_methods_allowed' => [],
    ];

    return [
        'sessionId' => 'test-session',
        'plan' => [
            'code' => 'yearly',
            'name' => 'Anual',
            'reason' => $preapprovalPlan['reason'],
            'amount' => $preapprovalPlan['auto_recurring']['transaction_amount'],
            'currency_id' => $preapprovalPlan['auto_recurring']['currency_id'],
            'frequency' => $preapprovalPlan['auto_recurring']['frequency'],
            'frequency_type' => $preapprovalPlan['auto_recurring']['frequency_type'],
            'monthly_equivalent' => round($preapprovalPlan['auto_recurring']['transaction_amount'] / 12, 2),
            'checkout_mode' => 'subscription_authorized',
            'preapproval_plan_id' => $preapprovalPlan['id'],
        ],
        'user' => (object) [
            'email' => 'teste@dinfy.app',
        ],
        'publicKey' => $publicKey,
        'completionUrl' => route('subscription.checkout.complete', ['session' => 'test-session']),
        'returnUrl' => $preapprovalPlan['back_url'],
        'errorMessage' => $publicKey === '' ? 'Configure a chave publica do Mercado Pago para liberar o checkout.' : null,
    ];
};

Route::get(
    '/subscriptions/checkout/session/{session}',
    [SubscriptionCheckoutSessionController::class, 'intro'],
)->name('subscription.checkout.page');

Route::get(
    '/subscriptions/checkout/session/{session}/payment',
    [SubscriptionCheckoutSessionController::class, 'show'],
)->name('subscription.checkout.form');

Route::get('/check', function () use ($testCheckoutData) {
    return view('subscriptions.intro', [
        ...$testCheckoutData(),
        'checkoutUrl' => route('subscription.checkout.test.form'),
    ]);
});

Route::get('/check/payment', function () use ($testCheckoutData) {
    return view('subscriptions.checkout', $testCheckoutData());
})->name('subscription.checkout.test.form');
