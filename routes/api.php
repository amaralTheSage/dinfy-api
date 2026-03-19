<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\FinancialBudgetController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/me', [MeController::class, 'update']);
    Route::post('/me/avatar', [MeController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [MeController::class, 'deleteAvatar']);

    Route::apiResource('accounts', FinancialAccountController::class);
    Route::apiResource('transactions', FinancialTransactionController::class)->only([
        'index',
        'show',
        'store',
    ]);
    Route::apiResource('budgets', FinancialBudgetController::class);
});
