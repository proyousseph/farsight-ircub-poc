<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail-closed: never honor payment simulation outside local/testing,
        // even if CHANNEL_ALLOW_SIMULATE was copied from a POC .env.
        if (! $this->app->environment(['local', 'testing'])) {
            config(['channels.allow_simulate' => false]);
        }
    }
}
