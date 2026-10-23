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
        // Railway terminates TLS before forwarding HTTP to the container.
        // Use the configured canonical HTTPS URL without trusting arbitrary client proxy headers.
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
