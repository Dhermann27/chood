<?php

namespace App\Providers;

use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Calendar::class, function () {
            $client = new GoogleClient();
            $client->useApplicationDefaultCredentials();   // reads GOOGLE_APPLICATION_CREDENTIALS
            $client->addScope(Calendar::CALENDAR);
            return new Calendar($client);
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
