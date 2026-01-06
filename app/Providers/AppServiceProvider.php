<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GeneralSettingsService;

use App\Models\ActivityLog;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;


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
        $modelPath = app_path('Models');

        if (File::isDirectory($modelPath)) {
            // $modelFiles = File::files($modelPath);
            
            // foreach ($modelFiles as $file) {
            //     $modelClass = 'App\\Models\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
                
            //     if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
            //         $modelClass::observe(ActivityLogObserver::class);
            //     }
            // }

            $modelFiles = File::files($modelPath);
            
            $excludedModels = [
                ActivityLog::class,
                \App\Models\LoginSession::class,
                // Add any other models you want to exclude from activity logging here
            ];

            foreach ($modelFiles as $file) {
                $modelClass = 'App\\Models\\' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
                
                if (in_array($modelClass, $excludedModels)) {
                    continue;
                }

                if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
                    $modelClass::observe(ActivityLogObserver::class);
                }
            }

        }
    }
}
