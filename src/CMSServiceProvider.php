<?php

namespace Webook\LaravelCMS;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Contracts\Http\Kernel;
use Webook\LaravelCMS\Http\Middleware\InjectToolbar;
use Webook\LaravelCMS\Http\Middleware\InjectEditableMarkers;
use Webook\LaravelCMS\Http\Middleware\InjectMetaTags;
use Webook\LaravelCMS\Http\Middleware\WrapTranslations;

class CMSServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cms.php', 'cms'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/cms-auth.php', 'cms'
        );

        // Register CMSAuthHandler as singleton
        $this->app->singleton(\Webook\LaravelCMS\Services\CMSAuthHandler::class);

        $this->app->singleton('cms.toolbar', function ($app) {
            return new InjectToolbar($app->make(\Webook\LaravelCMS\Services\CMSAuthHandler::class));
        });

        // Register CMS services
        $this->app->singleton(\Webook\LaravelCMS\Services\CMSLogger::class);
        $this->app->singleton(\Webook\LaravelCMS\Services\FileUpdater::class);
        $this->app->singleton(\Webook\LaravelCMS\Services\TranslationWrapper::class);
        $this->app->singleton(\Webook\LaravelCMS\Services\BladeSourceTracker::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cms.php' => config_path('cms.php'),
                __DIR__.'/../config/cms-auth.php' => config_path('cms-auth.php'),
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
        // Use 'web' middleware to access session for language detection
        Route::prefix('api')
            ->middleware('web')
            ->group(__DIR__.'/../routes/api.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms');

        // Register the middlewares
        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(WrapTranslations::class);
        $kernel->pushMiddleware(InjectMetaTags::class);
        $kernel->pushMiddleware(InjectEditableMarkers::class);
        $kernel->pushMiddleware(InjectToolbar::class);

        // Extend the translator to use our wrapper
        $this->extendTranslator();

        // Register Blade directives for translations
        $this->registerBladeDirectives();

        // Register Blade source tracking if feature is enabled
        if (config('cms.features.component_source_mapping')) {
            $this->app->make(\Webook\LaravelCMS\Services\BladeSourceTracker::class)->register();
        }
    }

    /**
     * Extend Laravel's translator to support CMS wrapping
     */
    protected function extendTranslator()
    {
        $this->app->extend('translator', function ($translator, $app) {
            $wrapper = $app->make(\Webook\LaravelCMS\Services\TranslationWrapper::class);

            // Store reference to original translator in wrapper
            $wrapper->originalTranslator = $translator;

            return $wrapper;
        });
    }

    /**
     * Register Blade directives for CMS translations
     */
    protected function registerBladeDirectives()
    {
        // @translation directive - wraps translated content to make it editable
        // Usage: @translation('welcome.message') or @translation('welcome.message', 'messages')
        Blade::directive('translation', function ($expression) {
            // Parse the expression to get key and optional file
            $parts = explode(',', $expression);
            $key = trim($parts[0], " \t\n\r\0\x0B'\"");
            $file = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B'\"") : null;

            // Generate a unique ID for this translation
            $id = 'trans-' . substr(md5($key), 0, 16);

            // Build the data attributes
            $attrs = "data-cms-editable=\"true\" data-cms-type=\"translation\" data-cms-id=\"{$id}\" data-translation-key=\"{$key}\"";
            if ($file) {
                $attrs .= " data-translation-file=\"{$file}\"";
            }

            return "<?php echo '<span ' . e('{$attrs}') . '>' . __({$expression}) . '</span>'; ?>";
        });

        // @trans directive - alias for @translation
        Blade::directive('trans', function ($expression) {
            $parts = explode(',', $expression);
            $key = trim($parts[0], " \t\n\r\0\x0B'\"");
            $file = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B'\"") : null;

            $id = 'trans-' . substr(md5($key), 0, 16);
            $attrs = "data-cms-editable=\"true\" data-cms-type=\"translation\" data-cms-id=\"{$id}\" data-translation-key=\"{$key}\"";
            if ($file) {
                $attrs .= " data-translation-file=\"{$file}\"";
            }

            return "<?php echo '<span ' . e('{$attrs}') . '>' . trans({$expression}) . '</span>'; ?>";
        });

        // @translateChoice directive - for plural translations
        Blade::directive('translateChoice', function ($expression) {
            // Parse expression like ('messages.apples', $count)
            preg_match("/\(\s*'([^']+)'\s*,\s*(.+?)\s*\)/", $expression, $matches);

            if (!empty($matches)) {
                $key = $matches[1];
                $count = $matches[2];

                $id = 'trans-' . substr(md5($key), 0, 16);
                $attrs = "data-cms-editable=\"true\" data-cms-type=\"translation\" data-cms-id=\"{$id}\" data-translation-key=\"{$key}\" data-translation-plural=\"true\"";

                return "<?php echo '<span ' . e('{$attrs}') . '>' . trans_choice('{$key}', {$count}) . '</span>'; ?>";
            }

            return "<?php echo trans_choice({$expression}); ?>";
        });

        // @cmsMetaTags directive - renders meta tags for SEO
        // Usage: @cmsMetaTags or @cmsMetaTags('/about', 'en')
        Blade::directive('cmsMetaTags', function ($expression) {
            if (empty($expression)) {
                return "<?php echo \Webook\LaravelCMS\Helpers\MetadataHelper::renderMetaTags(); ?>";
            }
            return "<?php echo \Webook\LaravelCMS\Helpers\MetadataHelper::renderMetaTags({$expression}); ?>";
        });
    }
}