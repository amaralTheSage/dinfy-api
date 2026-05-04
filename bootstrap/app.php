<?php

use App\Http\Middleware\EnsureAssistantSecret;
use App\Jobs\ExpireOverdueSubscriptionsJob;
use App\Jobs\SyncOpenFinanceStatementsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule
            ->call(fn () => ExpireOverdueSubscriptionsJob::dispatchSync())
            ->name('subscriptions:expire-overdue-daily')
            ->daily()
            ->withoutOverlapping();

        $schedule
            ->call(fn () => SyncOpenFinanceStatementsJob::dispatchSync())
            ->name('openfinance:sync-statements-hourly')
            ->hourly()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'assistant.secret' => EnsureAssistantSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
