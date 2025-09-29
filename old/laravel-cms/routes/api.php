<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\Api\ContentController;
use Webook\LaravelCMS\Http\Controllers\Api\TranslationController;
use Webook\LaravelCMS\Http\Controllers\Api\AssetController;
use Webook\LaravelCMS\Http\Controllers\Api\HistoryController;
use Webook\LaravelCMS\Http\Controllers\Api\SystemController;

/*
|--------------------------------------------------------------------------
| Laravel CMS API Routes
|--------------------------------------------------------------------------
|
| This file contains the API routes for the Laravel CMS package.
| All routes are versioned and require authentication by default.
| Rate limiting is applied based on the operation type and user permissions.
|
| API Version: v1
| Base URL: /api/v1/cms/
|
*/

/*
|--------------------------------------------------------------------------
| Content API Routes
|--------------------------------------------------------------------------
|
| Routes for managing editable content within the CMS.
| All content operations require authentication and appropriate permissions.
|
*/

Route::group([
    'prefix' => 'content',
    'as' => 'content.',
    'middleware' => ['cms.auth', 'cms.permission:cms.edit'],
], function () {

    // GET /api/content/scan - Scan page for editable content
    Route::get('/scan', [ContentController::class, 'scan'])
        ->name('scan')
        ->middleware('throttle:30,1');  // Conservative rate limit for scanning

    // GET /api/content/{key} - Get specific content
    Route::get('/{key}', [ContentController::class, 'show'])
        ->name('show')
        ->middleware('throttle:120,1')
        ->where('key', '[a-zA-Z0-9\-_.\/]+');

    // PUT /api/content/text - Update text content
    Route::put('/text', [ContentController::class, 'updateText'])
        ->name('update.text')
        ->middleware('throttle:60,1');

    // PUT /api/content/image - Update image content
    Route::put('/image', [ContentController::class, 'updateImage'])
        ->name('update.image')
        ->middleware('throttle:30,1');  // Lower rate limit for image operations

    // PUT /api/content/link - Update link content
    Route::put('/link', [ContentController::class, 'updateLink'])
        ->name('update.link')
        ->middleware('throttle:60,1');

    // POST /api/content/bulk - Bulk content updates
    Route::post('/bulk', [ContentController::class, 'bulkUpdate'])
        ->name('bulk.update')
        ->middleware('throttle:10,1');  // Very conservative for bulk operations

    // GET /api/content - List all editable content (with pagination)
    Route::get('/', [ContentController::class, 'index'])
        ->name('index')
        ->middleware('throttle:60,1');

    // POST /api/content/validate - Validate content before saving
    Route::post('/validate', [ContentController::class, 'validate'])
        ->name('validate')
        ->middleware('throttle:120,1');
});

/*
|--------------------------------------------------------------------------
| Translation API Routes
|--------------------------------------------------------------------------
|
| Routes for managing multi-language content and translations.
| Supports individual and bulk translation operations.
|
*/

Route::group([
    'prefix' => 'translations',
    'as' => 'translations.',
    'middleware' => ['cms.auth', 'cms.permission:cms.edit'],
], function () {

    // GET /api/translations/{locale} - Get all translations for locale
    Route::get('/{locale}', [TranslationController::class, 'index'])
        ->name('index')
        ->middleware('throttle:60,1')
        ->where('locale', '[a-z]{2}(_[A-Z]{2})?');

    // GET /api/translations/{locale}/{key} - Get specific translation
    Route::get('/{locale}/{key}', [TranslationController::class, 'show'])
        ->name('show')
        ->middleware('throttle:120,1')
        ->where([
            'locale' => '[a-z]{2}(_[A-Z]{2})?',
            'key' => '[a-zA-Z0-9\-_.\/]+',
        ]);

    // PUT /api/translations/{locale}/{key} - Update specific translation
    Route::put('/{locale}/{key}', [TranslationController::class, 'update'])
        ->name('update')
        ->middleware('throttle:60,1')
        ->where([
            'locale' => '[a-z]{2}(_[A-Z]{2})?',
            'key' => '[a-zA-Z0-9\-_.\/]+',
        ]);

    // POST /api/translations/sync - Sync translations across locales
    Route::post('/sync', [TranslationController::class, 'sync'])
        ->name('sync')
        ->middleware(['throttle:5,1', 'cms.permission:cms.admin']);

    // GET /api/translations/missing - Find missing translations
    Route::get('/missing', [TranslationController::class, 'missing'])
        ->name('missing')
        ->middleware('throttle:10,1');

    // POST /api/translations/import - Import translations from file
    Route::post('/import', [TranslationController::class, 'import'])
        ->name('import')
        ->middleware(['throttle:5,1', 'cms.permission:cms.admin']);

    // GET /api/translations/export/{locale} - Export translations for locale
    Route::get('/export/{locale}', [TranslationController::class, 'export'])
        ->name('export')
        ->middleware('throttle:10,1')
        ->where('locale', '[a-z]{2}(_[A-Z]{2})?');
});

/*
|--------------------------------------------------------------------------
| Asset Management Routes
|--------------------------------------------------------------------------
|
| Routes for managing uploaded assets like images, documents, etc.
| Includes browsing, uploading, and deletion functionality.
|
*/

Route::group([
    'prefix' => 'assets',
    'as' => 'assets.',
    'middleware' => ['cms.auth', 'cms.permission:cms.edit'],
], function () {

    // POST /api/assets/upload - Upload new asset
    Route::post('/upload', [AssetController::class, 'upload'])
        ->name('upload')
        ->middleware('throttle:20,1');  // Conservative for file uploads

    // GET /api/assets/browse - Browse uploaded assets
    Route::get('/browse', [AssetController::class, 'browse'])
        ->name('browse')
        ->middleware('throttle:60,1');

    // GET /api/assets/{id} - Get specific asset details
    Route::get('/{asset}', [AssetController::class, 'show'])
        ->name('show')
        ->middleware('throttle:120,1');

    // DELETE /api/assets/{id} - Delete asset
    Route::delete('/{asset}', [AssetController::class, 'destroy'])
        ->name('destroy')
        ->middleware(['throttle:30,1', 'cms.permission:cms.admin']);

    // PUT /api/assets/{id} - Update asset metadata
    Route::put('/{asset}', [AssetController::class, 'update'])
        ->name('update')
        ->middleware('throttle:60,1');

    // POST /api/assets/{id}/optimize - Optimize asset (resize, compress)
    Route::post('/{asset}/optimize', [AssetController::class, 'optimize'])
        ->name('optimize')
        ->middleware('throttle:10,1');

    // GET /api/assets/search - Search assets
    Route::get('/search', [AssetController::class, 'search'])
        ->name('search')
        ->middleware('throttle:60,1');
});

/*
|--------------------------------------------------------------------------
| History/Backup Routes
|--------------------------------------------------------------------------
|
| Routes for managing content history, backups, and restoration.
| Provides version control functionality for content changes.
|
*/

Route::group([
    'prefix' => 'history',
    'as' => 'history.',
    'middleware' => ['cms.auth', 'cms.permission:cms.edit'],
], function () {

    // GET /api/history - Get change history (paginated)
    Route::get('/', [HistoryController::class, 'index'])
        ->name('index')
        ->middleware('throttle:60,1');

    // GET /api/history/{id} - Get specific change details
    Route::get('/{history}', [HistoryController::class, 'show'])
        ->name('show')
        ->middleware('throttle:120,1');

    // GET /api/history/{id}/diff - Get diff of changes
    Route::get('/{history}/diff', [HistoryController::class, 'diff'])
        ->name('diff')
        ->middleware('throttle:60,1');

    // POST /api/history/{id}/restore - Restore from specific version
    Route::post('/{history}/restore', [HistoryController::class, 'restore'])
        ->name('restore')
        ->middleware(['throttle:5,1', 'cms.permission:cms.admin']);

    // GET /api/history/content/{key} - Get history for specific content
    Route::get('/content/{key}', [HistoryController::class, 'contentHistory'])
        ->name('content')
        ->middleware('throttle:60,1')
        ->where('key', '[a-zA-Z0-9\-_.\/]+');
});

/*
|--------------------------------------------------------------------------
| Backup Management Routes
|--------------------------------------------------------------------------
|
| Routes specifically for managing full system backups.
| Requires administrative permissions.
|
*/

Route::group([
    'prefix' => 'backups',
    'as' => 'backups.',
    'middleware' => ['cms.auth', 'cms.permission:cms.backup'],
], function () {

    // GET /api/backups - List all backups
    Route::get('/', [HistoryController::class, 'backups'])
        ->name('index')
        ->middleware('throttle:30,1');

    // POST /api/backups - Create new backup
    Route::post('/', [HistoryController::class, 'createBackup'])
        ->name('create')
        ->middleware('throttle:3,1');  // Very conservative for backup creation

    // POST /api/backups/{id}/restore - Restore from backup
    Route::post('/{backup}/restore', [HistoryController::class, 'restoreBackup'])
        ->name('restore')
        ->middleware(['throttle:1,5', 'cms.permission:cms.admin']);  // Very restrictive

    // DELETE /api/backups/{id} - Delete backup
    Route::delete('/{backup}', [HistoryController::class, 'deleteBackup'])
        ->name('destroy')
        ->middleware(['throttle:10,1', 'cms.permission:cms.admin']);

    // GET /api/backups/{id}/download - Download backup file
    Route::get('/{backup}/download', [HistoryController::class, 'downloadBackup'])
        ->name('download')
        ->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| System Management Routes
|--------------------------------------------------------------------------
|
| Routes for system administration, monitoring, and maintenance.
| Most require administrative permissions.
|
*/

Route::group([
    'prefix' => 'system',
    'as' => 'system.',
    'middleware' => ['cms.auth'],
], function () {

    // GET /api/system/status - CMS status and health check
    Route::get('/status', [SystemController::class, 'status'])
        ->name('status')
        ->middleware('throttle:60,1');

    // GET /api/system/permissions - Get current user permissions
    Route::get('/permissions', [SystemController::class, 'permissions'])
        ->name('permissions')
        ->middleware('throttle:120,1');

    // GET /api/system/config - Get public configuration
    Route::get('/config', [SystemController::class, 'config'])
        ->name('config')
        ->middleware(['throttle:60,1', 'cache.headers:public;max_age=300']);

    // Administrative routes (require admin permission)
    Route::group([
        'middleware' => 'cms.permission:cms.admin'
    ], function () {

        // POST /api/system/cache/clear - Clear CMS cache
        Route::post('/cache/clear', [SystemController::class, 'clearCache'])
            ->name('cache.clear')
            ->middleware('throttle:10,1');

        // GET /api/system/logs - Get system logs
        Route::get('/logs', [SystemController::class, 'logs'])
            ->name('logs')
            ->middleware('throttle:30,1');

        // POST /api/system/maintenance - Toggle maintenance mode
        Route::post('/maintenance', [SystemController::class, 'toggleMaintenance'])
            ->name('maintenance')
            ->middleware('throttle:5,1');

        // GET /api/system/analytics - Get usage analytics
        Route::get('/analytics', [SystemController::class, 'analytics'])
            ->name('analytics')
            ->middleware('throttle:20,1');

        // POST /api/system/optimize - Optimize system performance
        Route::post('/optimize', [SystemController::class, 'optimize'])
            ->name('optimize')
            ->middleware('throttle:3,1');
    });
});

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Routes for external integrations and webhooks.
| Used for git integration, deployment hooks, etc.
|
*/

Route::group([
    'prefix' => 'webhooks',
    'as' => 'webhooks.',
    'middleware' => ['throttle:100,1'],  // Higher limit for automated systems
], function () {

    // POST /api/webhooks/git - Git webhook for auto-sync
    Route::post('/git', [SystemController::class, 'gitWebhook'])
        ->name('git');

    // POST /api/webhooks/deploy - Deployment webhook
    Route::post('/deploy', [SystemController::class, 'deployWebhook'])
        ->name('deploy');

    // POST /api/webhooks/cache-purge - Cache purge webhook
    Route::post('/cache-purge', [SystemController::class, 'cachePurgeWebhook'])
        ->name('cache.purge');
});

/*
|--------------------------------------------------------------------------
| Development Routes
|--------------------------------------------------------------------------
|
| Routes available only in development/testing environments.
| These are automatically disabled in production.
|
*/

if (config('app.env') !== 'production') {
    Route::group([
        'prefix' => 'dev',
        'as' => 'dev.',
        'middleware' => ['cms.auth', 'throttle:60,1'],
    ], function () {

        // GET /api/dev/routes - List all CMS routes
        Route::get('/routes', [SystemController::class, 'routes'])
            ->name('routes');

        // GET /api/dev/config - Get full configuration (including sensitive data)
        Route::get('/config', [SystemController::class, 'fullConfig'])
            ->name('config');

        // POST /api/dev/test-email - Send test email
        Route::post('/test-email', [SystemController::class, 'testEmail'])
            ->name('test.email');

        // GET /api/dev/phpinfo - Show PHP info
        Route::get('/phpinfo', [SystemController::class, 'phpinfo'])
            ->name('phpinfo');
    });
}