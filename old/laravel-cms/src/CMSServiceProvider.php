<?php

namespace Webook\LaravelCMS;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Console\Scheduling\Schedule;
use Webook\LaravelCMS\Services\ContentScanner;
use Webook\LaravelCMS\Services\TranslationManager;
use Webook\LaravelCMS\Services\FileUpdater;
use Webook\LaravelCMS\Services\BackupManager;
use Webook\LaravelCMS\Services\AssetManager;
use Webook\LaravelCMS\Services\PermissionManager;
use Webook\LaravelCMS\Services\MediaAssetManager;
use Webook\LaravelCMS\Services\ImageProcessor;
use Webook\LaravelCMS\Services\DatabaseContentScanner;
use Webook\LaravelCMS\Services\DatabaseContentEditor;
use Webook\LaravelCMS\Services\SettingsManager;
use Webook\LaravelCMS\Services\PreviewService;
use Webook\LaravelCMS\Console\Commands\InstallCommand;
use Webook\LaravelCMS\Console\Commands\SetupCommand;
use Webook\LaravelCMS\Console\Commands\ClearCacheCommand;
use Webook\LaravelCMS\Console\Commands\BackupCommand;
use Webook\LaravelCMS\Console\Commands\RestoreCommand;
// Additional commands to be implemented
// use Webook\LaravelCMS\Console\Commands\RegenerateThumbnailsCommand;
// use Webook\LaravelCMS\Console\Commands\CleanupAssetsCommand;
// use Webook\LaravelCMS\Console\Commands\ScanDatabaseContentCommand;
use Webook\LaravelCMS\Http\Middleware\CMSAuthMiddleware;
use Webook\LaravelCMS\Http\Middleware\CMSPermissionMiddleware;
use Webook\LaravelCMS\Http\Middleware\InjectEditableMarkers;
use Webook\LaravelCMS\Http\Middleware\DetectDatabaseContent;
use Webook\LaravelCMS\Facades\CMS;
use Webook\LaravelCMS\Providers\CMSRouteServiceProvider;

/**
 * Laravel CMS Service Provider
 *
 * This service provider handles the registration and bootstrapping of the Laravel CMS package.
 * It implements a comprehensive setup including service bindings, middleware registration,
 * Blade directives, console commands, and asset publishing.
 *
 * The provider follows Laravel's service container patterns and implements proper
 * dependency injection for all CMS services.
 */
class CMSServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * These bindings use the singleton pattern to ensure that the same instance
     * is used throughout the application lifecycle, which is important for
     * services that maintain state or expensive initialization.
     *
     * @var array
     */
    public $bindings = [
        // Core CMS service interface bindings
        'Webook\LaravelCMS\Contracts\ContentScannerInterface' => ContentScanner::class,
        'Webook\LaravelCMS\Contracts\TranslationManagerInterface' => TranslationManager::class,
        'Webook\LaravelCMS\Contracts\FileUpdaterInterface' => FileUpdater::class,
        'Webook\LaravelCMS\Contracts\BackupManagerInterface' => BackupManager::class,
        'Webook\LaravelCMS\Contracts\AssetManagerInterface' => AssetManager::class,
        'Webook\LaravelCMS\Contracts\PermissionManagerInterface' => PermissionManager::class,
        'Webook\LaravelCMS\Contracts\MediaAssetManagerInterface' => MediaAssetManager::class,
        'Webook\LaravelCMS\Contracts\DatabaseContentScannerInterface' => DatabaseContentScanner::class,
        'Webook\LaravelCMS\Contracts\DatabaseContentEditorInterface' => DatabaseContentEditor::class,
    ];

    /**
     * All of the container singletons that should be registered.
     *
     * Singletons are instantiated once and reused for subsequent requests.
     * This is ideal for services that are expensive to create or maintain state.
     *
     * @var array
     */
    public $singletons = [
        ContentScanner::class,
        TranslationManager::class,
        FileUpdater::class,
        BackupManager::class,
        AssetManager::class,
        MediaAssetManager::class,
        ImageProcessor::class,
        DatabaseContentScanner::class,
        DatabaseContentEditor::class,
        SettingsManager::class,
    ];

    /**
     * Register any application services.
     *
     * This method is called early in the Laravel bootstrap process and is used
     * to register services into the container. It should not attempt to use
     * any other services as they may not be available yet.
     *
     * @return void
     */
    public function register(): void
    {
        // Register configuration file and merge with published config
        $this->registerConfiguration();

        // Register core CMS services using dependency injection
        $this->registerCoreServices();

        // Register console commands for CLI operations
        $this->registerConsoleCommands();

        // Register middleware for route protection
        $this->registerMiddleware();

        // Register the main CMS facade
        $this->registerFacades();

        // Register the dedicated route service provider
        $this->registerRouteServiceProvider();
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all service providers have been registered.
     * It's safe to use other services here, and this is where we set up
     * Blade directives, routes, event listeners, and other bootstrapping tasks.
     *
     * @return void
     */
    public function boot(): void
    {
        // Only boot CMS functionality if it's enabled in configuration
        if (!$this->cmsIsEnabled()) {
            return;
        }

        // Configure rate limiters first
        $this->configureRateLimiters();

        // Register Blade directives for template integration
        $this->registerBladeDirectives();

        // Register Blade components for reusable UI elements
        $this->registerBladeComponents();

        // Routes are handled by the dedicated CMSRouteServiceProvider
        // which provides advanced routing features like model binding and rate limiting

        // Register event listeners for CMS operations
        $this->registerEventListeners();

        // Register authorization gates and policies
        $this->registerAuthorization();

        // Register global middleware for auto-injection
        $this->registerGlobalMiddleware();

        // Publish assets and configuration files
        $this->registerPublishableAssets();

        // Register scheduled tasks
        $this->registerScheduledTasks();

        // Load package views, translations, and migrations
        $this->loadPackageResources();
    }

    /**
     * Register the configuration file and merge with application config.
     *
     * This allows users to publish and customize the configuration while
     * maintaining sensible defaults from the package.
     *
     * @return void
     */
    protected function registerConfiguration(): void
    {
        // Merge package configuration with published configuration
        $this->mergeConfigFrom(__DIR__.'/../config/cms.php', 'cms');
        $this->mergeConfigFrom(__DIR__.'/../config/cms-assets.php', 'cms-assets');
        $this->mergeConfigFrom(__DIR__.'/../config/cms-database.php', 'cms-database');
    }

    /**
     * Register core CMS services with proper dependency injection.
     *
     * Each service is registered as a singleton to maintain state and improve
     * performance. Services are bound to both their concrete class and interface
     * to support dependency injection through type-hinting.
     *
     * @return void
     */
    protected function registerCoreServices(): void
    {
        // Content Scanner Service - Scans and analyzes content files
        $this->app->singleton(ContentScanner::class, function ($app) {
            return new ContentScanner(
                $app['files'],
                $app['cache.store'],
                $app->make(\Illuminate\Http\Client\Factory::class),
                $app['config']->get('cms.content_scanner', [])
            );
        });

        // Translation Manager Service - Handles multi-language content
        $this->app->singleton(TranslationManager::class, function ($app) {
            return new TranslationManager(
                $app['translator'],
                $app['config']->get('cms.locale', []),
                $app['cache.store']
            );
        });

        // File Updater Service - Manages file operations and versioning
        $this->app->singleton(FileUpdater::class, function ($app) {
            return new FileUpdater(
                $app['files'],
                $app['cache.store'],
                $app['config']->get('cms.file_updater', [])
            );
        });

        // Backup Manager Service - Handles content backups and restoration
        $this->app->singleton(BackupManager::class, function ($app) {
            return new BackupManager(
                $app['filesystem'],
                $app['config']->get('cms.storage.backup', []),
                $app['log']
            );
        });

        // Asset Manager Service - Manages CSS/JS assets and optimization
        $this->app->singleton(AssetManager::class, function ($app) {
            return new AssetManager(
                $app['config']->get('cms.assets', []),
                $app['cache.store'],
                $app['files']
            );
        });

        // Permission Manager Service - Handles authorization and permissions
        $this->app->singleton(PermissionManager::class, function ($app) {
            return new PermissionManager(
                $app['auth'],
                $app['config']->get('cms.auth', [])
            );
        });

        // Media Asset Manager Service - Handles media upload, processing, and management
        $this->app->singleton(MediaAssetManager::class, function ($app) {
            return new MediaAssetManager(
                $app['config']->get('cms-assets', []),
                $app['filesystem'],
                $app->make(ImageProcessor::class)
            );
        });

        // Image Processor Service - Handles image manipulation and optimization
        $this->app->singleton(ImageProcessor::class, function ($app) {
            return new ImageProcessor(
                $app['config']->get('cms-assets.image_processing', [])
            );
        });

        // Database Content Scanner Service - Scans and analyzes database content
        $this->app->singleton(DatabaseContentScanner::class, function ($app) {
            return new DatabaseContentScanner(
                $app['db'],
                $app['config']->get('cms-database', []),
                $app['cache.store']
            );
        });

        // Database Content Editor Service - Handles direct database content editing
        $this->app->singleton(DatabaseContentEditor::class, function ($app) {
            return new DatabaseContentEditor(
                $app['db'],
                $app['config']->get('cms-database', []),
                $app->make(DatabaseContentScanner::class)
            );
        });

        // Preview Service - Handles content fetching and injection for preview
        $this->app->singleton(PreviewService::class, function ($app) {
            return new PreviewService();
        });

        // Register main CMS service that coordinates all other services
        $this->app->singleton('cms', function ($app) {
            return new \Webook\LaravelCMS\CMS(
                $app->make(ContentScanner::class),
                $app->make(TranslationManager::class),
                $app->make(FileUpdater::class),
                $app->make(BackupManager::class),
                $app->make(AssetManager::class),
                $app->make(PermissionManager::class),
                $app->make(MediaAssetManager::class),
                $app->make(DatabaseContentScanner::class),
                $app->make(DatabaseContentEditor::class)
            );
        });
    }

    /**
     * Register console commands for CLI operations.
     *
     * These commands provide administrative functionality for the CMS
     * including installation, setup, maintenance, and backup operations.
     *
     * @return void
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,              // cms:install - Initial CMS installation
                SetupCommand::class,                // cms:setup - Configure CMS settings
                ClearCacheCommand::class,           // cms:clear-cache - Clear CMS caches
                BackupCommand::class,               // cms:backup - Create content backup
                RestoreCommand::class,              // cms:restore - Restore from backup
                // Additional commands to be implemented:
                // RegenerateThumbnailsCommand::class, // cms:regenerate-thumbnails - Regenerate asset thumbnails
                // CleanupAssetsCommand::class,        // cms:cleanup-assets - Clean up orphaned assets
                // ScanDatabaseContentCommand::class,  // cms:scan-database - Scan database for editable content
            ]);
        }
    }

    /**
     * Register middleware for route protection and functionality.
     *
     * Middleware provides cross-cutting concerns like authentication,
     * authorization, and request/response modification for CMS routes.
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Register middleware aliases for easy usage in routes
        $router->aliasMiddleware('cms.auth', CMSAuthMiddleware::class);
        $router->aliasMiddleware('cms.permission', CMSPermissionMiddleware::class);
        $router->aliasMiddleware('cms.edit', InjectEditableMarkers::class);
        $router->aliasMiddleware('cms.inject', InjectEditableMarkers::class); // Alias for backward compatibility
        $router->aliasMiddleware('cms.database', DetectDatabaseContent::class);

        // Register middleware groups for common combinations
        $router->middlewareGroup('cms', [
            'web',
            'cms.auth',
            'cms.permission',
        ]);

        $router->middlewareGroup('cms.editor', [
            'web',
            'cms.auth',
            'cms.permission',
            'cms.edit',
        ]);

        $router->middlewareGroup('cms.database', [
            'web',
            'cms.auth',
            'cms.permission',
            'cms.database',
        ]);
    }

    /**
     * Register global middleware during boot process
     */
    protected function registerGlobalMiddleware(): void
    {
        $settingsManager = $this->app->make(SettingsManager::class);

        // Only add global middleware if auto-injection is enabled
        // Always add middleware for now to ensure it's working
        $this->app->make('router')->pushMiddlewareToGroup('web', InjectEditableMarkers::class);

        // TODO: Re-enable conditional loading later
        // if ($settingsManager->isAutoInjectEnabled()) {
        //     $this->app->make('router')->pushMiddlewareToGroup('web', InjectEditableMarkers::class);
        // }
    }

    /**
     * Register the main CMS facade.
     *
     * Facades provide a static interface to services in the container,
     * making them easy to use throughout the application.
     *
     * @return void
     */
    protected function registerFacades(): void
    {
        $this->app->alias('cms', CMS::class);
    }

    /**
     * Register Blade directives for template integration.
     *
     * These directives provide a clean, Laravel-like syntax for integrating
     * CMS functionality into Blade templates.
     *
     * @return void
     */
    protected function registerBladeDirectives(): void
    {
        // @cmseditable directive - Makes content editable inline
        Blade::directive('cmseditable', function ($expression) {
            return "<?php echo app('cms')->renderEditableContent({$expression}); ?>";
        });

        // @endcmseditable directive - Closes editable content block
        Blade::directive('endcmseditable', function () {
            return "<?php echo app('cms')->closeEditableContent(); ?>";
        });

        // @cmstoolbar directive - Renders the CMS editing toolbar
        Blade::directive('cmstoolbar', function ($expression = null) {
            return "<?php echo app('cms')->renderToolbar({$expression}); ?>";
        });

        // @cmsassets directive - Includes necessary CMS CSS/JS assets
        Blade::directive('cmsassets', function ($expression = null) {
            return "<?php echo app('cms')->renderAssets({$expression}); ?>";
        });

        // @cmsconfig directive - Outputs configuration as JSON for frontend
        Blade::directive('cmsconfig', function ($expression = null) {
            return "<?php echo app('cms')->renderConfig({$expression}); ?>";
        });

        // Database content directives
        Blade::directive('cmsModel', function ($expression) {
            return "<?php echo app('cms.database')->markModel({$expression}); ?>";
        });

        Blade::directive('cmsField', function ($expression) {
            return "<?php echo app('cms.database')->markField({$expression}); ?>";
        });

        Blade::directive('cmsCollection', function ($expression) {
            return "<?php echo app('cms.database')->markCollection({$expression}); ?>";
        });

        // Asset directives
        Blade::directive('cmsAsset', function ($expression) {
            return "<?php echo app('cms.assets')->renderAsset({$expression}); ?>";
        });

        Blade::directive('cmsGallery', function ($expression) {
            return "<?php echo app('cms.assets')->renderGallery({$expression}); ?>";
        });

        Blade::directive('cmsImageEditor', function ($expression = null) {
            return "<?php echo view('cms::partials.image-editor', {$expression})->render(); ?>";
        });

        // @cmsauth directive - Check if user has CMS permissions
        Blade::if('cmsauth', function ($permission = null) {
            return app(PermissionManager::class)->check($permission);
        });

        // @cmsguest directive - Check if user lacks CMS permissions
        Blade::if('cmsguest', function () {
            return !app(PermissionManager::class)->check();
        });
    }

    /**
     * Register Blade components for reusable UI elements.
     *
     * Components provide encapsulated, reusable pieces of UI that can be
     * used throughout the application with consistent styling and behavior.
     *
     * @return void
     */
    protected function registerBladeComponents(): void
    {
        // Register CMS-specific Blade components
        Blade::componentNamespace('Webook\\LaravelCMS\\View\\Components', 'cms');

        // Anonymous components from views/components directory
        Blade::anonymousComponentNamespace('cms-views::components', 'cms');
    }

    /**
     * Register the dedicated CMS Route Service Provider.
     *
     * The CMSRouteServiceProvider handles all routing concerns including
     * route model binding, rate limiting, parameter validation, and
     * route caching compatibility.
     *
     * @return void
     */
    protected function registerRouteServiceProvider(): void
    {
        $this->app->register(CMSRouteServiceProvider::class);
    }

    /**
     * Configure rate limiters for CMS routes.
     *
     * @return void
     */
    protected function configureRateLimiters(): void
    {
        // Log to verify this method is being called
        \Log::info('CMS: Configuring rate limiters');

        // General CMS rate limits
        RateLimiter::for('cms-general', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
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

        // Asset upload operations
        RateLimiter::for('cms-upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Asset browsing
        RateLimiter::for('cms-browse', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Add other missing rate limiters used by CMS routes
        RateLimiter::for('cms-translation', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-image', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-delete', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-history', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-dev', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Additional rate limiters for all routes
        RateLimiter::for('cms-settings', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-settings-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-validate', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-sync', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-analysis', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-import', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-export', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-diff', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-restore', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-backup', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-backup-create', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-backup-restore', function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-backup-delete', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-backup-download', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-status', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-permissions', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-config', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-cache', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-logs', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-maintenance', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-analytics', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-optimize', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-webhook', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cms-public', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    }

    /**
     * Register event listeners for CMS operations.
     *
     * Event listeners provide a way to respond to CMS actions like content
     * updates, user actions, and system events with custom logic.
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        // Listen for content update events
        Event::listen(
            'cms.content.updated',
            'Webook\\LaravelCMS\\Listeners\\ContentUpdatedListener'
        );

        // Listen for backup creation events
        Event::listen(
            'cms.backup.created',
            'Webook\\LaravelCMS\\Listeners\\BackupCreatedListener'
        );

        // Listen for user permission changes
        Event::listen(
            'cms.permissions.changed',
            'Webook\\LaravelCMS\\Listeners\\PermissionsChangedListener'
        );

        // Listen for cache invalidation events
        Event::listen(
            'cms.cache.invalidate',
            'Webook\\LaravelCMS\\Listeners\\CacheInvalidationListener'
        );
    }

    /**
     * Register authorization gates and policies.
     *
     * Gates and policies provide fine-grained access control for CMS
     * functionality based on user roles and permissions.
     *
     * @return void
     */
    protected function registerAuthorization(): void
    {
        $permissions = $this->app['config']->get('cms.auth.permissions', []);

        // Register gates for CMS permissions
        foreach ($permissions as $action => $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return app(PermissionManager::class)->userCan($user, $permission);
            });
        }

        // Register main CMS access gate
        Gate::define(
            $this->app['config']->get('cms.auth.gate', 'manage-cms'),
            'Webook\\LaravelCMS\\Policies\\CMSPolicy@manage'
        );
    }

    /**
     * Register publishable asset groups.
     *
     * This allows users to publish and customize various parts of the package
     * including configuration, views, translations, and assets.
     *
     * @return void
     */
    protected function registerPublishableAssets(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration files
            $this->publishes([
                __DIR__.'/../config/cms.php' => config_path('cms.php'),
                __DIR__.'/../config/cms-assets.php' => config_path('cms-assets.php'),
                __DIR__.'/../config/cms-database.php' => config_path('cms-database.php'),
            ], 'cms-config');

            // Publish CSS and JavaScript assets
            $this->publishes([
                __DIR__.'/../resources/js' => public_path('vendor/cms/js'),
                __DIR__.'/../resources/css' => public_path('vendor/cms/css'),
            ], 'cms-assets');

            // Publish Blade view templates
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/cms'),
            ], 'cms-views');

            // Publish translation files
            $this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/cms'),
            ], 'cms-translations');

            // Publish database migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cms-migrations');

            // Publish all assets at once
            $this->publishes([
                __DIR__.'/../config/cms.php' => config_path('cms.php'),
                __DIR__.'/../config/cms-assets.php' => config_path('cms-assets.php'),
                __DIR__.'/../config/cms-database.php' => config_path('cms-database.php'),
                __DIR__.'/../resources/js' => public_path('vendor/cms/js'),
                __DIR__.'/../resources/css' => public_path('vendor/cms/css'),
                __DIR__.'/../resources/views' => resource_path('views/vendor/cms'),
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/cms'),
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cms');
        }
    }

    /**
     * Register scheduled tasks for automated CMS operations.
     *
     * Scheduled tasks handle routine maintenance like cache clearing,
     * backup creation, and cleanup operations.
     *
     * @return void
     */
    protected function registerScheduledTasks(): void
    {
        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $config = $this->app['config']->get('cms');

            // Schedule automatic backups if enabled
            if ($config['storage']['backup']['enabled'] ?? false) {
                $schedule->command('cms:backup')
                    ->daily()
                    ->at('02:00')
                    ->withoutOverlapping()
                    ->runInBackground();
            }

            // Schedule cache cleanup
            if ($config['cache']['enabled'] ?? false) {
                $schedule->command('cms:clear-cache --expired')
                    ->hourly()
                    ->withoutOverlapping();
            }

            // Schedule old backup cleanup
            $retentionDays = $config['storage']['backup']['retention_days'] ?? 30;
            if ($retentionDays > 0) {
                $schedule->command("cms:backup --cleanup --days={$retentionDays}")
                    ->daily()
                    ->at('03:00');
            }
        });
    }

    /**
     * Load package resources like views, translations, and migrations.
     *
     * This makes package resources available to the Laravel application
     * while allowing them to be overridden by published versions.
     *
     * @return void
     */
    protected function loadPackageResources(): void
    {
        // Load package views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms');

        // Load package translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'cms');

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load package routes (handled in registerRoutes method)
        // Routes are loaded there to have access to configuration
    }

    /**
     * Check if CMS functionality is enabled.
     *
     * This allows the CMS to be disabled completely in certain environments
     * or configurations without affecting the rest of the application.
     *
     * @return bool
     */
    protected function cmsIsEnabled(): bool
    {
        return $this->app['config']->get('cms.enabled', true);
    }

    /**
     * Get the services provided by the provider.
     *
     * This method tells Laravel which services this provider offers,
     * which helps with service resolution and dependency injection.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'cms',
            ContentScanner::class,
            TranslationManager::class,
            FileUpdater::class,
            BackupManager::class,
            AssetManager::class,
            PermissionManager::class,
        ];
    }
}