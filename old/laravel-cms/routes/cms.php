<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\EditorController;
use Webook\LaravelCMS\Http\Controllers\PreviewController;
use Webook\LaravelCMS\Http\Controllers\SettingsController;
use Webook\LaravelCMS\Http\Controllers\Api\ContentController;
use Webook\LaravelCMS\Http\Controllers\Api\TranslationController;
use Webook\LaravelCMS\Http\Controllers\Api\AssetController;
use Webook\LaravelCMS\Http\Controllers\Api\HistoryController;
use Webook\LaravelCMS\Http\Controllers\Api\SystemController;

/*
|--------------------------------------------------------------------------
| Laravel CMS Routes
|--------------------------------------------------------------------------
|
| This file contains all routes for the Laravel CMS package.
| Routes are organized by functionality and include proper middleware,
| rate limiting, and route model binding for optimal performance and security.
|
| All routes are automatically prefixed and middleware is applied via
| the CMSRouteServiceProvider configuration.
|
*/

/*
|--------------------------------------------------------------------------
| Editor Interface Routes
|--------------------------------------------------------------------------
|
| These routes provide the main CMS editor interface for content management.
| Requires authentication and CMS permissions.
|
*/

Route::group([
    'prefix' => 'editor',
    'as' => 'editor.',
    'middleware' => array_filter([config('cms.api.auth.required', false) ? 'cms.auth' : null, config('cms.api.auth.required', false) ? 'cms.permission:cms.edit' : null]),
], function () {

    // GET /editor - Main editor interface
    Route::get('/', [EditorController::class, 'index'])
        ->name('index')
        ->middleware('throttle:cms-general');

    // GET /editor/preview/{url} - Preview with edit markers
    Route::get('/preview/{url}', [PreviewController::class, 'show'])
        ->name('preview')
        ->where('url', '.*')  // Allow full URL paths
        ->middleware('throttle:cms-read');

    // GET /editor/config - Get editor configuration
    Route::get('/config', [EditorController::class, 'config'])
        ->name('config')
        ->middleware(['throttle:cms-config', 'cache.headers:public;max_age=3600']);
});

/*
|--------------------------------------------------------------------------
| Settings Management Routes
|--------------------------------------------------------------------------
|
| Routes for configuring CMS settings, access controls, and system
| preferences. Requires admin permissions.
|
*/

Route::group([
    'prefix' => 'settings',
    'as' => 'settings.',
    'middleware' => array_filter(['web', config('cms.api.auth.required', false) ? 'cms.auth' : null, config('cms.api.auth.required', false) ? 'cms.permission:cms.admin' : null]),
], function () {

    // GET /settings - Settings interface
    Route::get('/', [SettingsController::class, 'index'])
        ->name('index')
        ->middleware('throttle:cms-general');

    // PUT /settings - Update settings
    Route::put('/', [SettingsController::class, 'update'])
        ->name('update')
        ->middleware('throttle:cms-settings');

    // POST /settings/reset - Reset to defaults
    Route::post('/reset', [SettingsController::class, 'reset'])
        ->name('reset')
        ->middleware('throttle:cms-settings-reset:3,1');

    // GET /settings/json - Get settings as JSON
    Route::get('/json', [SettingsController::class, 'show'])
        ->name('show')
        ->middleware('throttle:cms-general');

    // POST /settings/route/add - Add excluded route
    Route::post('/route/add', [SettingsController::class, 'addExcludedRoute'])
        ->name('route.add')
        ->middleware('throttle:cms-settings');

    // DELETE /settings/route/remove - Remove excluded route
    Route::delete('/route/remove', [SettingsController::class, 'removeExcludedRoute'])
        ->name('route.remove')
        ->middleware('throttle:cms-settings');

    // POST /settings/route/test - Test route accessibility
    Route::post('/route/test', [SettingsController::class, 'testRoute'])
        ->name('test-route')
        ->middleware('throttle:cms-settings');

    // GET /settings/system - System information
    Route::get('/system', [SettingsController::class, 'systemInfo'])
        ->name('system')
        ->middleware('throttle:cms-general');
});

/*
|--------------------------------------------------------------------------
| Content API Routes
|--------------------------------------------------------------------------
|
| API endpoints for managing editable content. All routes require
| authentication and appropriate CMS permissions.
|
*/

Route::group([
    'prefix' => 'api/content',
    'as' => 'api.content.',
    'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null, config('cms.api.auth.required', false) ? 'cms.permission:cms.edit' : null]),
], function () {

    // GET /api/content/scan - Scan page for editable content
    Route::get('/scan', [ContentController::class, 'scan'])
        ->name('scan')
        ->middleware('throttle:cms-scan');

    // GET /api/content/{key} - Get specific content
    Route::get('/{contentKey}', [ContentController::class, 'show'])
        ->name('show')
        ->middleware('throttle:cms-read')
        ->where('contentKey', '[a-zA-Z0-9\-_.\/]+');

    // PUT /api/content/text - Update text content
    Route::put('/text', [ContentController::class, 'updateText'])
        ->name('update.text')
        ->middleware('throttle:cms-update');

    // PUT /api/content/image - Update image content
    Route::put('/image', [ContentController::class, 'updateImage'])
        ->name('update.image')
        ->middleware('throttle:cms-image');

    // PUT /api/content/link - Update link content
    Route::put('/link', [ContentController::class, 'updateLink'])
        ->name('update.link')
        ->middleware('throttle:cms-update');

    // POST /api/content/bulk - Bulk content updates
    Route::post('/bulk', [ContentController::class, 'bulkUpdate'])
        ->name('bulk.update')
        ->middleware('throttle:cms-bulk');

    // Additional content management routes
    Route::get('/', [ContentController::class, 'index'])
        ->name('index')
        ->middleware('throttle:cms-read');

    Route::post('/validate', [ContentController::class, 'validateContent'])
        ->name('validate')
        ->middleware('throttle:cms-validate');
});

/*
|--------------------------------------------------------------------------
| Translation API Routes
|--------------------------------------------------------------------------
|
| Routes for managing multi-language content and translations.
| Supports comprehensive translation management including sync operations.
|
*/

Route::group([
    'prefix' => 'api/translations',
    'as' => 'api.translations.',
    'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null, config('cms.api.auth.required', false) ? 'cms.permission:cms.edit' : null]),
], function () {

    // GET /api/translations/{locale} - Get all translations for locale
    Route::get('/{locale}', [TranslationController::class, 'index'])
        ->name('index')
        ->middleware('throttle:cms-translation')
        ->where('locale', '[a-z]{2}(-[A-Z]{2})?');

    // GET /api/translations/{locale}/{key} - Get specific translation
    Route::get('/{locale}/{translationKey}', [TranslationController::class, 'show'])
        ->name('show')
        ->middleware('throttle:cms-translation')
        ->where([
            'locale' => '[a-z]{2}(-[A-Z]{2})?',
            'translationKey' => '[a-zA-Z0-9\-_.\/]+',
        ]);

    // PUT /api/translations/{locale}/{key} - Update specific translation
    Route::put('/{locale}/{translationKey}', [TranslationController::class, 'update'])
        ->name('update')
        ->middleware('throttle:cms-translation')
        ->where([
            'locale' => '[a-z]{2}(-[A-Z]{2})?',
            'translationKey' => '[a-zA-Z0-9\-_.\/]+',
        ]);

    // POST /api/translations/sync - Sync translations across locales
    Route::post('/sync', [TranslationController::class, 'sync'])
        ->name('sync')
        ->middleware(['throttle:cms-sync', 'cms.permission:cms.admin']);

    // GET /api/translations/missing - Find missing translations
    Route::get('/missing', [TranslationController::class, 'missing'])
        ->name('missing')
        ->middleware('throttle:cms-analysis');

    // Additional translation routes
    Route::post('/import', [TranslationController::class, 'import'])
        ->name('import')
        ->middleware(['throttle:cms-import', 'cms.permission:cms.admin']);

    Route::get('/export/{locale}', [TranslationController::class, 'export'])
        ->name('export')
        ->middleware('throttle:cms-export')
        ->where('locale', '[a-z]{2}(-[A-Z]{2})?');
});

/*
|--------------------------------------------------------------------------
| Asset Management Routes
|--------------------------------------------------------------------------
|
| Routes for managing uploaded assets including images and documents.
| Provides comprehensive asset lifecycle management.
|
*/

Route::group([
    'prefix' => 'api/assets',
    'as' => 'api.assets.',
    'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null, config('cms.api.auth.required', false) ? 'cms.permission:cms.edit' : null]),
], function () {

    // POST /api/assets/upload - Upload new asset
    Route::post('/upload', [AssetController::class, 'upload'])
        ->name('upload')
        ->middleware('throttle:cms-upload');

    // GET /api/assets/browse - Browse uploaded assets
    Route::get('/browse', [AssetController::class, 'browse'])
        ->name('browse')
        ->middleware('throttle:cms-browse');

    // DELETE /api/assets/{id} - Delete asset
    Route::delete('/{asset}', [AssetController::class, 'destroy'])
        ->name('destroy')
        ->middleware(['throttle:cms-delete', 'cms.permission:cms.admin']);

    // Additional asset routes
    Route::get('/{asset}', [AssetController::class, 'show'])
        ->name('show')
        ->middleware('throttle:cms-read');

    Route::put('/{asset}', [AssetController::class, 'update'])
        ->name('update')
        ->middleware('throttle:cms-update');

    Route::post('/{asset}/optimize', [AssetController::class, 'optimize'])
        ->name('optimize')
        ->middleware('throttle:cms-optimize');

    Route::get('/search', [AssetController::class, 'search'])
        ->name('search')
        ->middleware('throttle:cms-search');
});

/*
|--------------------------------------------------------------------------
| History/Backup Routes
|--------------------------------------------------------------------------
|
| Routes for managing content history, backups, and restoration.
| Provides comprehensive version control functionality.
|
*/

Route::group([
    'prefix' => 'api',
    'as' => 'api.',
    'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null]),
], function () {

    // History routes
    Route::group([
        'prefix' => 'history',
        'as' => 'history.',
        'middleware' => 'cms.permission:cms.edit',
    ], function () {

        // GET /api/history - Get change history
        Route::get('/', [HistoryController::class, 'index'])
            ->name('index')
            ->middleware('throttle:cms-history');

        // GET /api/history/{id} - Get specific change
        Route::get('/{history}', [HistoryController::class, 'show'])
            ->name('show')
            ->middleware('throttle:cms-history');

        // GET /api/diff/{id} - Get diff of changes
        Route::get('/{history}/diff', [HistoryController::class, 'diff'])
            ->name('diff')
            ->middleware('throttle:cms-diff');

        // Additional history routes
        Route::get('/content/{contentKey}', [HistoryController::class, 'contentHistory'])
            ->name('content')
            ->middleware('throttle:cms-history:60,1')
            ->where('contentKey', '[a-zA-Z0-9\-_.\/]+');
    });

    // Restore routes (require admin permission)
    Route::group([
        'middleware' => 'cms.permission:cms.admin',
    ], function () {

        // POST /api/restore/{id} - Restore from backup
        Route::post('/restore/{history}', [HistoryController::class, 'restore'])
            ->name('restore')
            ->middleware('throttle:cms-restore');
    });

    // Backup management routes
    Route::group([
        'prefix' => 'backups',
        'as' => 'backups.',
        'middleware' => 'cms.permission:cms.backup',
    ], function () {

        Route::get('/', [HistoryController::class, 'backups'])
            ->name('index')
            ->middleware('throttle:cms-backup');

        Route::post('/', [HistoryController::class, 'createBackup'])
            ->name('create')
            ->middleware('throttle:cms-backup-create:3,1');

        Route::post('/{backup}/restore', [HistoryController::class, 'restoreBackup'])
            ->name('restore')
            ->middleware(['throttle:cms-backup-restore:1,5', 'cms.permission:cms.admin']);

        Route::delete('/{backup}', [HistoryController::class, 'deleteBackup'])
            ->name('destroy')
            ->middleware(['throttle:cms-backup-delete:10,1', 'cms.permission:cms.admin']);

        Route::get('/{backup}/download', [HistoryController::class, 'downloadBackup'])
            ->name('download')
            ->middleware('throttle:cms-backup-download:5,1');
    });
});

/*
|--------------------------------------------------------------------------
| System Routes
|--------------------------------------------------------------------------
|
| Routes for system administration, monitoring, and maintenance.
| Includes status checks, cache management, and permission queries.
|
*/

Route::group([
    'prefix' => 'api',
    'as' => 'api.',
    'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null]),
], function () {

    // GET /api/status - CMS status and health
    Route::get('/status', [SystemController::class, 'status'])
        ->name('status')
        ->middleware('throttle:cms-status');

    // GET /api/permissions - Get user permissions
    Route::get('/permissions', [SystemController::class, 'permissions'])
        ->name('permissions')
        ->middleware('throttle:cms-permissions');

    // GET /api/config - Get public configuration
    Route::get('/config', [SystemController::class, 'config'])
        ->name('config')
        ->middleware(['throttle:cms-config', 'cache.headers:public;max_age=300']);

    // Administrative routes
    Route::group([
        'middleware' => 'cms.permission:cms.admin',
    ], function () {

        // POST /api/cache/clear - Clear CMS cache
        Route::post('/cache/clear', [SystemController::class, 'clearCache'])
            ->name('cache.clear')
            ->middleware('throttle:cms-cache');

        // Additional admin routes
        Route::get('/logs', [SystemController::class, 'logs'])
            ->name('logs')
            ->middleware('throttle:cms-logs');

        Route::post('/maintenance', [SystemController::class, 'toggleMaintenance'])
            ->name('maintenance')
            ->middleware('throttle:cms-maintenance');

        Route::get('/analytics', [SystemController::class, 'analytics'])
            ->name('analytics')
            ->middleware('throttle:cms-analytics');

        Route::post('/optimize', [SystemController::class, 'optimize'])
            ->name('optimize')
            ->middleware('throttle:cms-optimize');
    });
});

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Routes for external integrations and webhooks.
| These routes have special rate limiting for automated systems.
|
*/

Route::group([
    'prefix' => 'api/webhooks',
    'as' => 'api.webhooks.',
    'middleware' => ['api'],  // No auth required for webhooks
], function () {

    // Webhooks use token-based authentication instead of user auth
    Route::post('/git', [SystemController::class, 'gitWebhook'])
        ->name('git')
        ->middleware('throttle:cms-webhook');

    Route::post('/deploy', [SystemController::class, 'deployWebhook'])
        ->name('deploy')
        ->middleware('throttle:cms-webhook');

    Route::post('/cache-purge', [SystemController::class, 'cachePurgeWebhook'])
        ->name('cache.purge')
        ->middleware('throttle:cms-webhook');
});

/*
|--------------------------------------------------------------------------
| Development Routes
|--------------------------------------------------------------------------
|
| Routes available only in development/testing environments.
| Automatically disabled in production for security.
|
*/

if (config('app.env') !== 'production') {
    Route::group([
        'prefix' => 'api/dev',
        'as' => 'api.dev.',
        'middleware' => array_filter(['api', config('cms.api.auth.required', false) ? 'cms.auth' : null, 'throttle:cms-dev']),
    ], function () {

        Route::get('/routes', [SystemController::class, 'routes'])
            ->name('routes');

        Route::get('/config', [SystemController::class, 'fullConfig'])
            ->name('config');

        Route::post('/test-email', [SystemController::class, 'testEmail'])
            ->name('test.email');

        Route::get('/phpinfo', [SystemController::class, 'phpinfo'])
            ->name('phpinfo');

        Route::get('/debug', [SystemController::class, 'debug'])
            ->name('debug');
    });
}

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Public routes that don't require authentication but may have rate limiting.
| Used for health checks and public configuration.
|
*/

Route::group([
    'prefix' => 'api/public',
    'as' => 'api.public.',
    'middleware' => ['api'],
], function () {

    // Public health check
    Route::get('/health', [SystemController::class, 'publicHealth'])
        ->name('health')
        ->middleware('throttle:cms-public');

    // Public version info
    Route::get('/version', [SystemController::class, 'version'])
        ->name('version')
        ->middleware('throttle:cms-public');

    // Public configuration (safe data only)
    Route::get('/config', [SystemController::class, 'publicConfig'])
        ->name('config')
        ->middleware(['throttle:cms-public', 'cache.headers:public;max_age=3600']);
});