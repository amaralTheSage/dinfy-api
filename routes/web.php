<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionCheckoutSessionController;

Route::get(
    '/subscriptions/checkout/session/{session}',
    [SubscriptionCheckoutSessionController::class, 'show'],
)->name('subscription.checkout.page');
