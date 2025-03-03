<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeneralSettingsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('FileUploadService', function () {
            return new \App\Services\FileUploadService();
        });

        $this->app->singleton(GeneralSettingsService::class, function ($app) {
            return new GeneralSettingsService();
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
