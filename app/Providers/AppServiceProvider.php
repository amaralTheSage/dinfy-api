<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use WorkOS\WorkOS;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkOS::class, function (): WorkOS {
            return new WorkOS(
                apiKey: trim((string) config('services.workos.api_key', '')),
                clientId: trim((string) config('services.workos.client_id', '')),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
