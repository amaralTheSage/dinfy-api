<?php

use App\Services\Subscriptions\ExpireOverdueSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:expire', function () {
    $expiredCount = app(ExpireOverdueSubscriptions::class)->handle();

    $this->info(sprintf('Found %d overdue subscriptions.', $expiredCount));
    $this->info('Overdue subscriptions expired and user summaries synchronized.');
})->purpose('Expire overdue subscriptions and reconcile user subscription summary');
