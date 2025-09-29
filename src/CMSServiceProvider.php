<?php

namespace Webook\LaravelCMS;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Http\Kernel;
use Webook\LaravelCMS\Http\Middleware\InjectToolbar;
use Webook\LaravelCMS\Http\Middleware\InjectEditableMarkers;

class CMSServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cms.php', 'cms'
        );

        $this->app->singleton('cms.toolbar', function ($app) {
            return new InjectToolbar();
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cms.php' => config_path('cms.php'),
            ], 'cms-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/cms'),
            ], 'cms-views');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/cms'),
            ], 'cms-assets');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cms-migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load API routes with api prefix
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms');

        // Register the middlewares
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(InjectEditableMarkers::class);
        $kernel->pushMiddleware(InjectToolbar::class);
    }
}