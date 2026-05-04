<?php

use App\Http\Controllers\AssistantWebhookController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinancialBudgetController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\OpenFinanceController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/oauth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['workos'])
        ->name('auth.oauth.redirect');
    Route::get('/oauth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['workos'])
        ->name('auth.oauth.callback');
    Route::post('/oauth/exchange', [SocialAuthController::class, 'exchange'])
        ->name('auth.oauth.exchange');
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/me', [MeController::class, 'update']);
    Route::put('/me/whatsapp', [MeController::class, 'updateWhatsApp']);
    Route::post('/me/avatar', [MeController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [MeController::class, 'deleteAvatar']);

    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions/current/cancel', [SubscriptionController::class, 'cancel']);

    Route::apiResource('accounts', FinancialAccountController::class);

    Route::prefix('openfinance')->group(function () {
        Route::post('/connect', [OpenFinanceController::class, 'connect']);
        Route::get('/accounts/{account}/remote', [OpenFinanceController::class, 'showRemoteAccount']);
    });

    Route::apiResource('transactions', FinancialTransactionController::class)->only([
        'index',
        'show',
        'store',
        'update',
        'destroy',
    ]);
    Route::apiResource('budgets', FinancialBudgetController::class);
});

Route::prefix('assistant')
    ->middleware('assistant.secret')
    ->group(function () {
        Route::post('/context', [AssistantWebhookController::class, 'context']);
        Route::post('/execute', [AssistantWebhookController::class, 'execute']);
    });

Route::post('/mercado-pago/webhook', MercadoPagoWebhookController::class);
