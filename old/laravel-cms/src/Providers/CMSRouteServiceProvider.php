<?php

namespace Webook\LaravelCMS\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * CMS Route Service Provider
 *
 * Handles routing configuration specific to the Laravel CMS package.
 * Provides route model binding, rate limiting, and route caching
 * compatibility for optimal performance.
 */
class CMSRouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/cms/editor';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureRouteModelBinding();

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map(): void
    {
        $this->mapCmsRoutes();
    }

    /**
     * Define the CMS routes for the application.
     *
     * These routes all receive the configured route prefix, middleware,
     * and namespace from the main CMS configuration.
     *
     * @return void
     */
    protected function mapCmsRoutes(): void
    {
        $config = config('cms.routes', []);

        // Get middleware, handling auth requirements
        if (!config('cms.api.auth.required', false)) {
            // When auth is not required, use minimal middleware to avoid forcing authentication
            $middleware = ['throttle:cms-general'];
        } else {
            // When auth is required, use the configured middleware
            $middleware = $config['middleware'] ?? ['web', 'auth'];
        }

        Route::group([
            'middleware' => $middleware,
            'prefix' => $config['prefix'] ?? 'cms',
            'namespace' => 'Webook\\LaravelCMS\\Http\\Controllers',
            'domain' => $config['domain'] ?? null,
            'as' => 'cms.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../../routes/cms.php');
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        // General CMS rate limits
        RateLimiter::for('cms-general', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Content scanning - more restrictive due to resource intensity
        RateLimiter::for('cms-scan', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many scan requests. Please wait before trying again.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Content reading - higher limit for frequent operations
        RateLimiter::for('cms-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Content updates - moderate limit
        RateLimiter::for('cms-update', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Image operations - more restrictive due to processing requirements
        RateLimiter::for('cms-image', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Bulk operations - very restrictive
        RateLimiter::for('cms-bulk', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Bulk operations are rate limited. Please wait before trying again.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Translation operations
        RateLimiter::for('cms-translation', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Sync operations - very restrictive
        RateLimiter::for('cms-sync', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Sync operations are highly rate limited for system stability.',
                        'retry_after' => 300
                    ], 429);
                });
        });

        // Asset upload operations
        RateLimiter::for('cms-upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Asset browsing
        RateLimiter::for('cms-browse', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Deletion operations - restrictive
        RateLimiter::for('cms-delete', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Search operations
        RateLimiter::for('cms-search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // History operations
        RateLimiter::for('cms-history', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Diff operations - can be resource intensive
        RateLimiter::for('cms-diff', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Restore operations - very restrictive
        RateLimiter::for('cms-restore', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Restore operations are highly restricted for data safety.',
                        'retry_after' => 300
                    ], 429);
                });
        });

        // Backup operations
        RateLimiter::for('cms-backup', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Backup creation - very restrictive
        RateLimiter::for('cms-backup-create', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Backup restore - extremely restrictive
        RateLimiter::for('cms-backup-restore', function (Request $request) {
            return Limit::per(1, 5)->by($request->user()?->id ?: $request->ip());
        });

        // Backup deletion
        RateLimiter::for('cms-backup-delete', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Backup download
        RateLimiter::for('cms-backup-download', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // System status checks
        RateLimiter::for('cms-status', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Permission checks - high frequency
        RateLimiter::for('cms-permissions', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Configuration access
        RateLimiter::for('cms-config', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Cache operations
        RateLimiter::for('cms-cache', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Administrative operations
        RateLimiter::for('cms-admin', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Import/Export operations
        RateLimiter::for('cms-import', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Analysis operations
        RateLimiter::for('cms-analysis', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Optimization operations
        RateLimiter::for('cms-optimize', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // Validation operations
        RateLimiter::for('cms-validate', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Webhook operations - for automated systems
        RateLimiter::for('cms-webhook', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Development operations
        RateLimiter::for('cms-dev', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Public API operations
        RateLimiter::for('cms-public', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // Maintenance operations
        RateLimiter::for('cms-maintenance', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Analytics operations
        RateLimiter::for('cms-analytics', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Log viewing
        RateLimiter::for('cms-logs', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure route model binding for CMS entities.
     *
     * @return void
     */
    protected function configureRouteModelBinding(): void
    {
        // Content key binding with validation
        Route::bind('contentKey', function ($value) {
            // Validate content key format
            if (!preg_match('/^[a-zA-Z0-9\-_.\/]+$/', $value)) {
                abort(404, 'Invalid content key format');
            }

            // Additional validation could include checking if content exists
            return $value;
        });

        // Translation key binding
        Route::bind('translationKey', function ($value) {
            if (!preg_match('/^[a-zA-Z0-9\-_.\/]+$/', $value)) {
                abort(404, 'Invalid translation key format');
            }

            return $value;
        });

        // Locale binding with validation
        Route::bind('locale', function ($value) {
            $availableLocales = config('cms.locale.available', ['en']);

            if (!in_array($value, $availableLocales)) {
                abort(404, 'Locale not supported');
            }

            return $value;
        });

        // Asset model binding (if using Eloquent models)
        Route::model('asset', config('cms.models.asset', 'Webook\\LaravelCMS\\Models\\Asset'));

        // History model binding
        Route::model('history', config('cms.models.history', 'Webook\\LaravelCMS\\Models\\History'));

        // Backup model binding
        Route::model('backup', config('cms.models.backup', 'Webook\\LaravelCMS\\Models\\Backup'));

        // Custom URL parameter binding for preview routes
        Route::bind('url', function ($value) {
            // Decode the URL parameter
            $url = urldecode($value);

            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
                // Allow relative URLs starting with /
                abort(404, 'Invalid URL format');
            }

            return $url;
        });
    }

    /**
     * Get the route middleware to apply to all CMS routes.
     *
     * @return array
     */
    protected function getRouteMiddleware(): array
    {
        return array_merge(
            config('cms.routes.middleware', ['web']),
            ['throttle:cms-general']
        );
    }

    /**
     * Get the rate limit for API routes.
     *
     * @return string
     */
    protected function getApiRateLimit(): string
    {
        return config('cms.api.rate_limit', '1000,60');
    }

    /**
     * Determine if route caching is enabled and compatible.
     *
     * @return bool
     */
    protected function routeCacheEnabled(): bool
    {
        return config('cms.routes.cache_enabled', true) && !$this->app->routesAreCached();
    }
}